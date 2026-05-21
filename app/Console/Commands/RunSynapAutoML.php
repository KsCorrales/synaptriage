<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Services\SynapCores\Exceptions\SynapCoresException;
use App\Services\SynapCores\SynapCoresService;
use Illuminate\Console\Command;

class RunSynapAutoML extends Command
{
    protected $signature = 'synap:run-automl';

    protected $description = 'Runs AUTOML.PREDICT on all pending tickets and persists predictions';

    public function __construct(private readonly SynapCoresService $synapCores)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $experimentId = config('services.synapcores.experiment_id');

        if (! $experimentId) {
            $this->error('SYNAPCORES_EXPERIMENT_ID is not set in .env');
            return self::FAILURE;
        }

        $tickets = SupportTicket::where('triage_status', 'pending')->get();

        if ($tickets->isEmpty()) {
            $this->info('No pending tickets found.');
            return self::SUCCESS;
        }

        $this->info("{$tickets->count()} pending tickets found. Running AUTOML.PREDICT...");
        $bar = $this->output->createProgressBar($tickets->count());
        $bar->start();

        $dataset = $tickets->map(fn ($ticket) => [
            'id'                         => $ticket->id,
            'category'                   => $ticket->category,
            'customer_tier'              => $ticket->customer_tier,
            'response_time_expectation'  => $ticket->response_time_expectation,
        ])->toArray();

        try {
            $predictions = $this->synapCores->runAutoMlPredict($experimentId, $dataset);

            foreach ($predictions as $index => $prediction) {
                $tickets[$index]->update([
                    'predicted_priority' => $prediction->predictedClass,
                    'confidence_score'   => $prediction->confidence,
                    'triage_status'      => 'complete',
                ]);
                $bar->advance();
            }

            $bar->finish();
            $this->info('');
            $this->info('All predictions persisted successfully.');

            return self::SUCCESS;

        } catch (SynapCoresException $e) {
            $this->error("SynapCores error [{$e->getStatusCode()}]: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}