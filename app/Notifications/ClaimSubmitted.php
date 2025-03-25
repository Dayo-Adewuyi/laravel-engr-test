<?php

namespace App\Notifications;

use App\Models\Batch;
use App\Models\Claim;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

 
    public function __construct(public Batch $batch, public ?Claim $claim = null)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('New Claim Batch Submitted')
            ->greeting('Hello!')
            ->line("A new batch has been submitted:")
            ->line("Batch ID: {$this->batch->batch_identifier}")
            ->line("Date: {$this->batch->submission_date}")
            ->line("Total Claims: {$this->batch->total_claims}")
            ->line("Total Amount: $" . number_format($this->batch->total_amount, 2));

        if ($this->claim) {
            $mailMessage->line("")
                ->line("Most recent claim details:")
                ->line("Claim ID: {$this->claim->id}")
                ->line("Provider: {$this->claim->provider->name}")
                ->line("Specialty: {$this->claim->specialty->name}")
                ->line("Encounter Date: {$this->claim->encounter_date}");
        }

        return $mailMessage->action('View in Portal', url('/'))
            ->line('Thank you for using our claims processing system.');
    }

   
    public function toArray(object $notifiable): array
    {
        return [
            'batch_id' => $this->batch->id,
            'batch_identifier' => $this->batch->batch_identifier,
            'insurer_id' => $this->batch->insurer_id,
            'submission_date' => $this->batch->submission_date,
            'total_claims' => $this->batch->total_claims,
            'total_amount' => $this->batch->total_amount,
            'claim_id' => $this->claim ? $this->claim->id : null,
        ];
    }
}