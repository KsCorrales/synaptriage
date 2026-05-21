<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Services\SynapCores\DTOs\ExperimentConfig;
use App\Services\SynapCores\Exceptions\SynapCoresException;
use App\Services\SynapCores\SynapCoresService;
use Illuminate\Console\Command;

class SeedSynapExperiment extends Command
{
    protected $signature = 'synap:seed-experiment';

    protected $description = 'Creates a SynapCores experiment and trains the model on the support tickets dataset';

    public function __construct(private readonly SynapCoresService $synapCores)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Preparing dataset...');

        $dataset = SupportTicket::select([
            'id',
            'category',
            'customer_tier',
            'response_time_expectation',
            'priority',
        ])->get()->toArray();

        if (empty($dataset)) {
            $this->error('No tickets found. Run php artisan db:seed first.');
            return self::FAILURE;
        }

        $this->info(count($dataset) . ' tickets loaded.');

        try {
            $this->info('Creating experiment...');

            $config = new ExperimentConfig(
                name:         'support-ticket-triage',
                targetColumn: 'priority',
                modelType:    'classification',
                features:     ['category', 'customer_tier', 'response_time_expectation'],
                description:  'Classifies support ticket priority based on category, tier and response time expectation.',
            );

            $experimentId = $this->synapCores->createExperiment($config);
            $this->info("Experiment created: {$experimentId}");

            $this->info('Training model...');
            $this->synapCores->train($experimentId);
            $this->info('Model trained successfully.');

            $this->info('');
            $this->components->twoColumnDetail('Experiment ID', $experimentId);
            $this->info('Save this ID in your .env as SYNAPCORES_EXPERIMENT_ID');

            return self::SUCCESS;

        } catch (SynapCoresException $e) {
            $this->error("SynapCores error [{$e->getStatusCode()}]: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}