<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Claim;
use App\Models\Insurer;
use App\Models\Provider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimBatchingService
{
    /**
     * Process a new claim and add it to a batch.
     *
     * @param Claim $claim The claim to process
     * @return Batch|null The batch the claim was added to, or null if not batched
     */
    public function processClaim(Claim $claim): ?Batch
    {
        $insurer = $claim->insurer;
        $provider = $claim->provider;

        $batchDate = $insurer->prefers_encounter_date
            ? $claim->encounter_date
            : $claim->submission_date;
        $batchDate = Carbon::parse($batchDate);

        $batchIdentifier = "{$provider->name} {$batchDate->format('M j Y')}";

        try {
            return DB::transaction(function () use ($claim, $insurer, $provider, $batchDate, $batchIdentifier) {
                $batch = Batch::where('provider_id', $provider->id)
                    ->where('insurer_id', $insurer->id)
                    ->where('batch_date', $batchDate->format('Y-m-d'))
                    ->where('processed', false)
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    $existingBatchesToday = Batch::where('insurer_id', $insurer->id)
                        ->where('processed', false)
                        ->whereDate('processing_date', $batchDate->copy()->addDay())
                        ->sum('total_claims');

                    if (($existingBatchesToday + 1) >= $insurer->daily_capacity) {
                        $processingDate = $batchDate->copy()->addDays(2);
                    }
                    $batch = Batch::create([
                        'provider_id' => $provider->id,
                        'insurer_id' => $insurer->id,
                        'batch_date' => $batchDate->format('Y-m-d'),
                        'batch_identifier' => $batchIdentifier,
                        'total_claims' => 0,
                        'total_amount' => 0,
                        'processing_cost' => 0,
                        'processed' => false,
                        'processing_date' => $processingDate ?? $batchDate->copy()->addDay()->format('Y-m-d'),
                    ]);
                }

                $claim->batch_id = $batch->id;
                $claim->save();

                $batchTotals = Claim::where('batch_id', $batch->id)
                    ->select(DB::raw('COUNT(*) as claim_count, SUM(total_amount) as total_amount'))
                    ->first();

                $batch->total_claims = $batchTotals->claim_count;
                $batch->total_amount = $batchTotals->total_amount;

                $batch->processing_cost = $this->calculateBatchProcessingCost($batch);
                $batch->save();

                if ($batch->total_claims >= $insurer->max_batch_size) {
                    $this->createNewBatchIfNeeded($provider, $insurer, $batchDate);
                }

                return $batch;
            }, 3);
        } catch (\Exception $e) {
            Log::error('Error processing claim for batching: ' . $e->getMessage(), [
                'claim_id' => $claim->id,
                'provider_id' => $provider->id,
                'insurer_id' => $insurer->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Create a new batch if needed for future claims.
     *
     * @param Provider $provider
     * @param Insurer $insurer
     * @param Carbon $batchDate
     * @return Batch|null
     */
    protected function createNewBatchIfNeeded(Provider $provider, Insurer $insurer, Carbon $batchDate): ?Batch
    {
        $existingOpenBatch = Batch::where('provider_id', $provider->id)
            ->where('insurer_id', $insurer->id)
            ->where('batch_date', $batchDate->format('Y-m-d'))
            ->where('processed', false)
            ->where('total_claims', '<', $insurer->max_batch_size)
            ->exists();

        if (!$existingOpenBatch) {
            $batchIdentifier = "{$provider->name} {$batchDate->format('M j Y')} #" .
                (Batch::where('provider_id', $provider->id)
                    ->where('batch_date', $batchDate->format('Y-m-d'))
                    ->count() + 1);
            return Batch::create([
                'provider_id' => $provider->id,
                'insurer_id' => $insurer->id,
                'batch_date' => $batchDate->format('Y-m-d'),
                'batch_identifier' => $batchIdentifier,
                'total_claims' => 0,
                'total_amount' => 0,
                'processing_cost' => 0,
                'processed' => false,
                'processing_date' => $batchDate->copy()->addDay()->format('Y-m-d'),
            ]);
        }

        return null;
    }

    /**
     * Calculate the processing cost for a batch based on insurer constraints.
     * Optimized for performance with eager loading.
     *
     * @param Batch $batch The batch to calculate the cost for
     * @return float The calculated processing cost
     */
    public function calculateBatchProcessingCost(Batch $batch): float
    {
        if (!$batch->relationLoaded('insurer') || !$batch->relationLoaded('claims')) {
            $batch->load(['insurer.specialties', 'claims.specialty']);
        }

        $insurer = $batch->insurer;
        $processingDate = Carbon::parse($batch->processing_date);
        $dayOfMonth = $processingDate->day;
        $daysInMonth = $processingDate->daysInMonth;

        $baseTimeOfMonthFactor = 0.2 + min(0.3, max(0, 0.3 * ($dayOfMonth - 1) / max(1, ($daysInMonth - 1))));

        $specialtyFactors = [];
        foreach ($insurer->specialties as $specialty) {
            $specialtyFactors[$specialty->id] = $specialty->pivot->efficiency_factor;
        }

        $totalCost = 0.0;
        $claimCount = count($batch->claims);

        foreach ($batch->claims as $claim) {
            $specialtyId = $claim->specialty_id;
            $specialtyFactor = $specialtyFactors[$specialtyId] ?? 1.0;

            
            $priorityLevel = min(5, max(1, $claim->priority_level));
            $priorityFactor = 1.0 + (($priorityLevel - 1) * 0.2);

            $monetaryValueFactor = 1.0 + log10(max(1, min(1000000, $claim->total_amount))) * 0.1;

            $claimCost = $claim->total_amount * $baseTimeOfMonthFactor * $specialtyFactor * $priorityFactor * $monetaryValueFactor;

            $totalCost += $claimCost;
        }

        $optimalSize = ($insurer->min_batch_size + $insurer->max_batch_size) / 2;
        $sizeDifference = abs($claimCount - $optimalSize) / max($optimalSize, 1); 

        if ($claimCount < $insurer->min_batch_size) {
            $batchSizeFactor = 1.0 + min(0.2, (0.2 * $sizeDifference));
        } elseif ($claimCount > $insurer->max_batch_size) {
            $batchSizeFactor = 1.0 + min(0.15, (0.15 * $sizeDifference));
        } else {
            $batchSizeFactor = 1.0 - min(0.1, (0.1 * (1 - $sizeDifference)));
        }

        return round($totalCost * $batchSizeFactor, 2);
    }

    /**
     * Process all pending batches that are ready for processing.
     * Optimized with chunking for large datasets.
     * 
     * @return array Array of processed batch IDs and their stats
     */
    public function processReadyBatches(): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $processedBatches = [];

        DB::transaction(function () use ($today, &$processedBatches) {
            $insurerProcessingCounts = Batch::where('processing_date', $today)
                ->where('processed', true)
                ->select('insurer_id', DB::raw('SUM(total_claims) as processing_count'))
                ->groupBy('insurer_id')
                ->pluck('processing_count', 'insurer_id')
                ->toArray();

            $insurerCapacities = Insurer::pluck('daily_capacity', 'id')->toArray();
            $insurerMinBatchSizes = Insurer::pluck('min_batch_size', 'id')->toArray();

            Batch::where('processed', false)
                ->where('processing_date', '<=', $today)
                ->with('insurer') 
                ->orderBy('processing_date')
                ->orderBy('created_at') 
                ->chunk(100, function ($batches) use ($today, &$processedBatches, $insurerProcessingCounts, $insurerCapacities, $insurerMinBatchSizes) {
                    foreach ($batches as $batch) {
                        $insurerId = $batch->insurer_id;

                        if ($batch->total_claims < ($insurerMinBatchSizes[$insurerId] ?? 5)) {
                            continue;
                        }

                        $currentProcessingCount = $insurerProcessingCounts[$insurerId] ?? 0;

                        if (($currentProcessingCount + $batch->total_claims) > ($insurerCapacities[$insurerId] ?? 100)) {
                            $batch->processing_date = Carbon::parse($today)->addDay()->format('Y-m-d');
                            $batch->save();
                            continue;
                        }

                        $batch->processed = true;
                        $batch->save();

                        Claim::where('batch_id', $batch->id)
                            ->update(['processed' => true]);

                        $insurerProcessingCounts[$insurerId] = ($insurerProcessingCounts[$insurerId] ?? 0) + $batch->total_claims;

                        $processedBatches[] = [
                            'id' => $batch->id,
                            'identifier' => $batch->batch_identifier,
                            'claims' => $batch->total_claims,
                            'amount' => $batch->total_amount,
                            'cost' => $batch->processing_cost,
                        ];
                    }
                });
        });

        return $processedBatches;
    }

    /**
     * Re-optimize batches to minimize processing costs.
     * This can be run periodically to rebalance batches before they are processed.
     * 
     * @return array Stats on optimization results
     */
    public function reoptimizeBatches(): array
    {
        $stats = [
            'batches_analyzed' => 0,
            'batches_modified' => 0,
            'cost_before' => 0,
            'cost_after' => 0,
        ];

        $today = Carbon::now()->format('Y-m-d');

        // Find all unprocessed batches for tomorrow's processing
        $tomorrowDate = Carbon::parse($today)->addDay()->format('Y-m-d');

        // Use a transaction to ensure consistency
        DB::transaction(function () use ($tomorrowDate, &$stats) {
            // Get all insurers and their processing constraints
            $insurers = Insurer::all()->keyBy('id');

            // Group batches by insurer for targeted optimization
            $batchesByInsurer = Batch::where('processed', false)
                ->where('processing_date', $tomorrowDate)
                ->with(['claims.specialty', 'provider'])
                ->get()
                ->groupBy('insurer_id');

            foreach ($batchesByInsurer as $insurerId => $batches) {
                $insurer = $insurers[$insurerId];
                $stats['batches_analyzed'] += $batches->count();

                // Record original cost
                $originalCost = $batches->sum('processing_cost');
                $stats['cost_before'] += $originalCost;

                // Only proceed if there are enough batches to optimize
                if ($batches->count() < 2) {
                    continue;
                }

                // Find batches that are either too small or too large
                $tooSmallBatches = $batches->filter(function ($batch) use ($insurer) {
                    return $batch->total_claims < $insurer->min_batch_size;
                });

                $tooLargeBatches = $batches->filter(function ($batch) use ($insurer) {
                    return $batch->total_claims > $insurer->max_batch_size;
                });

                // Rebalance too-small batches
                $this->rebalanceSmallBatches($tooSmallBatches, $batches, $insurer);

                // Rebalance too-large batches
                $this->rebalanceLargeBatches($tooLargeBatches, $batches, $insurer);

                // Recalculate costs after optimization
                $updatedBatches = Batch::whereIn('id', $batches->pluck('id'))
                    ->where('processed', false)
                    ->get();

                $newCost = $updatedBatches->sum('processing_cost');
                $stats['cost_after'] += $newCost;

                if ($originalCost != $newCost) {
                    $stats['batches_modified'] += $updatedBatches->count();
                }
            }
        });

        $stats['savings'] = $stats['cost_before'] - $stats['cost_after'];
        $stats['savings_percentage'] = $stats['cost_before'] > 0
            ? round(($stats['savings'] / $stats['cost_before']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Rebalance batches that are too small.
     *
     * @param \Illuminate\Support\Collection $smallBatches
     * @param \Illuminate\Support\Collection $allBatches
     * @param Insurer $insurer
     */
    protected function rebalanceSmallBatches($smallBatches, $allBatches, Insurer $insurer): void
    {
        foreach ($smallBatches as $smallBatch) {
            if ($smallBatch->total_claims == 0) {
                $smallBatch->delete();
                continue;
            }

            $candidateBatches = $allBatches->filter(function ($batch) use ($smallBatch, $insurer) {
                return $batch->id != $smallBatch->id &&
                    $batch->provider_id == $smallBatch->provider_id &&
                    $batch->total_claims < $insurer->max_batch_size;
            })->sortBy(function ($batch) use ($insurer) {
                $optimalSize = ($insurer->min_batch_size + $insurer->max_batch_size) / 2;
                return abs($batch->total_claims - $optimalSize);
            });

            if ($candidateBatches->isEmpty()) {
                continue;
            }

            $targetBatch = $candidateBatches->first();

            if (($targetBatch->total_claims + $smallBatch->total_claims) <= $insurer->max_batch_size) {
                Claim::where('batch_id', $smallBatch->id)
                    ->update(['batch_id' => $targetBatch->id]);

                $this->updateBatchTotals($targetBatch);

                $smallBatch->delete();
            } else {
                $claimsToMove = min(
                    $smallBatch->total_claims,
                    $insurer->max_batch_size - $targetBatch->total_claims
                );

                if ($claimsToMove > 0) {
                    $claims = Claim::where('batch_id', $smallBatch->id)
                        ->limit($claimsToMove)
                        ->get();

                    foreach ($claims as $claim) {
                        $claim->batch_id = $targetBatch->id;
                        $claim->save();
                    }

                    $this->updateBatchTotals($targetBatch);
                    $this->updateBatchTotals($smallBatch);
                }
            }
        }
    }

    /**
     * Rebalance batches that are too large.
     *
     * @param \Illuminate\Support\Collection $largeBatches
     * @param \Illuminate\Support\Collection $allBatches
     * @param Insurer $insurer
     */
    protected function rebalanceLargeBatches($largeBatches, $allBatches, Insurer $insurer): void
    {
        foreach ($largeBatches as $largeBatch) {
            if ($largeBatch->total_claims <= ($insurer->max_batch_size + 2)) {
                continue;
            }

            $candidateBatches = $allBatches->filter(function ($batch) use ($largeBatch, $insurer) {
                return $batch->id != $largeBatch->id &&
                    $batch->provider_id == $largeBatch->provider_id &&
                    $batch->total_claims < $insurer->max_batch_size;
            })->sortBy('total_claims'); 

            if ($candidateBatches->isEmpty()) {
                $newBatch = $this->createNewBatchForProvider(
                    $largeBatch->provider,
                    $insurer,
                    Carbon::parse($largeBatch->batch_date)
                );

                $excessClaims = $largeBatch->total_claims - $insurer->max_batch_size;

                $claims = Claim::where('batch_id', $largeBatch->id)
                    ->orderBy('priority_level') 
                    ->limit($excessClaims)
                    ->get();

                foreach ($claims as $claim) {
                    $claim->batch_id = $newBatch->id;
                    $claim->save();
                }

                $this->updateBatchTotals($largeBatch);
                $this->updateBatchTotals($newBatch);
            } else {
                $excessClaims = $largeBatch->total_claims - $insurer->max_batch_size;
                $claimsMoved = 0;

                foreach ($candidateBatches as $targetBatch) {
                    $spaceAvailable = $insurer->max_batch_size - $targetBatch->total_claims;
                    $claimsToMove = min($excessClaims - $claimsMoved, $spaceAvailable);

                    if ($claimsToMove <= 0) {
                        break;
                    }

                    $claims = Claim::where('batch_id', $largeBatch->id)
                        ->orderBy('priority_level')
                        ->limit($claimsToMove)
                        ->get();

                    foreach ($claims as $claim) {
                        $claim->batch_id = $targetBatch->id;
                        $claim->save();
                    }

                    $this->updateBatchTotals($targetBatch);

                    $claimsMoved += $claimsToMove;

                    if ($claimsMoved >= $excessClaims) {
                        break;
                    }
                }

                $this->updateBatchTotals($largeBatch);

                if ($claimsMoved < $excessClaims) {
                    $newBatch = $this->createNewBatchForProvider(
                        $largeBatch->provider,
                        $insurer,
                        Carbon::parse($largeBatch->batch_date)
                    );

                    $remainingClaims = $excessClaims - $claimsMoved;

                    $claims = Claim::where('batch_id', $largeBatch->id)
                        ->orderBy('priority_level')
                        ->limit($remainingClaims)
                        ->get();

                    foreach ($claims as $claim) {
                        $claim->batch_id = $newBatch->id;
                        $claim->save();
                    }

                    $this->updateBatchTotals($largeBatch);
                    $this->updateBatchTotals($newBatch);
                }
            }
        }
    }

    /**
     * Create a new batch for a provider.
     *
     * @param Provider $provider
     * @param Insurer $insurer
     * @param Carbon $batchDate
     * @return Batch
     */
    protected function createNewBatchForProvider(Provider $provider, Insurer $insurer, Carbon $batchDate): Batch
    {
        $batchIdentifier = "{$provider->name} {$batchDate->format('M j Y')} #" .
            (Batch::where('provider_id', $provider->id)
                ->where('batch_date', $batchDate->format('Y-m-d'))
                ->count() + 1);

        return Batch::create([
            'provider_id' => $provider->id,
            'insurer_id' => $insurer->id,
            'batch_date' => $batchDate->format('Y-m-d'),
            'batch_identifier' => $batchIdentifier,
            'total_claims' => 0,
            'total_amount' => 0,
            'processing_cost' => 0,
            'processed' => false,
            'processing_date' => $batchDate->copy()->addDay()->format('Y-m-d'),
        ]);
    }

    /**
     * Update the totals for a batch.
     *
     * @param Batch $batch
     * @return void
     */
    protected function updateBatchTotals(Batch $batch): void
    {
        $totals = Claim::where('batch_id', $batch->id)
            ->select(DB::raw('COUNT(*) as claim_count, SUM(total_amount) as total_amount'))
            ->first();

        $batch->total_claims = $totals->claim_count;
        $batch->total_amount = $totals->total_amount;
        $batch->processing_cost = $this->calculateBatchProcessingCost($batch);
        $batch->save();
    }
}
