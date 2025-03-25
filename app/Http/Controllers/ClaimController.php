<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaimRequest;
use App\Models\Claim;
use App\Models\ClaimItem;
use App\Models\Insurer;
use App\Models\Provider;
use App\Models\Specialty;
use App\Services\ClaimBatchingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClaimController extends Controller
{
    protected $batchingService;

    public function __construct(ClaimBatchingService $batchingService)
    {
        $this->batchingService = $batchingService;
    }

    /**
     * Display the claims submission form.
     */
    public function create()
    {
        return Inertia::render('Claims/SubmitClaim', [
            'specialties' => Specialty::select('id', 'name', 'code')->get(),
            'insurers' => Insurer::select('id', 'name', 'code')->get(),
        ]);
    }

    /**
     * Store a newly created claim.
     */
    public function store(StoreClaimRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validated();
        
        $provider = $user->provider;
        
        $insurer = Insurer::where('code', $validated['insurer_code'])->first();
        
        try {
            return DB::transaction(function () use ($validated, $provider, $insurer, $user) {
                $claim = Claim::create([
                    'provider_id' => $provider->id,
                    'insurer_id' => $insurer->id,
                    'specialty_id' => $validated['specialty_id'],
                    'encounter_date' => $validated['encounter_date'],
                    'submission_date' => Carbon::now()->format('Y-m-d'),
                    'priority_level' => $validated['priority_level'],
                    'total_amount' => $validated['total_amount'],
                    'processed' => false,
                ]);
                
                foreach ($validated['items'] as $itemData) {
                    ClaimItem::create([
                        'claim_id' => $claim->id,
                        'name' => $itemData['name'],
                        'unit_price' => $itemData['unit_price'],
                        'quantity' => $itemData['quantity'],
                        'subtotal' => $itemData['subtotal'],
                    ]);
                }
                
                $batch = $this->batchingService->processClaim($claim);
                
                Notification::route('mail', $insurer->email)
                    ->notify(new \App\Notifications\ClaimSubmitted( $batch, $claim));
                
                return Redirect::route('claims.index')
                    ->with('success', 'Claim submitted successfully! Batch ID: ' . ($batch ? $batch->batch_identifier : 'Not batched yet'));
            });
        } catch (\Exception $e) {
            return Redirect::route('claims.index')
                ->with('error', 'Error submitting claim: ' . $e->getMessage());
        }
    }

    /**
     * Display the list of claims for the current provider.
     */
    public function index()
    {
        $user = Auth::user();
        
        $query = Claim::query()
            ->with(['provider', 'insurer', 'specialty', 'batch'])
            ->orderBy('created_at', 'desc');
            
        if ($user->provider_id) {
            $query->where('provider_id', $user->provider_id);
        }
        
        $claims = $query->paginate(10);
        
        return Inertia::render('Claims/Index', [
            'claims' => $claims,
        ]);
    }

    /**
     * Display the specified claim.
     */
    public function show(Claim $claim)
    {
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user->isAdmin() && $user->provider_id !== $claim->provider_id) {
            abort(403, 'Unauthorized action.');
        }
        
        $claim->load(['provider', 'insurer', 'specialty', 'batch', 'items']);
        
        return Inertia::render('Claims/Show', [
            'claim' => $claim,
        ]);
    }
}