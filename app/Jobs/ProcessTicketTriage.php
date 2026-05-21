<?php

namespace App\Jobs;

use App\Models\SupportTicket;
use App\Services\SynapCores\Exceptions\SynapCoresException;
use App\Services\SynapCores\SynapCoresService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTicketTriage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private readonly SupportTicket $ticket) {}

    public function handle(SynapCoresService $synapCores): void
    {
        $experimentId = config('services.synapcores.experiment_id');

        if (! $experimentId) {
            $this->fail('SYNAPCORES_EXPERIMENT_ID is not configured.');
            return;
        }

        try {
            $prediction = $synapCores->predict($experimentId, [
                'category'                  => $this->ticket->category,
                'customer_tier'             => $this->ticket->customer_tier,
                'response_time_expectation' => $this->ticket->response_time_expectation,
            ]);

            $this->ticket->update([
                'predicted_priority' => $prediction->predictedClass,
                'confidence_score'   => $prediction->confidence,
                'triage_status'      => 'complete',
            ]);

        } catch (SynapCoresException $e) {
            $this->ticket->update(['triage_status' => 'failed']);
            $this->fail($e);
        }
    }
}