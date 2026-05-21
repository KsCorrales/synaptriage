<?php

namespace Tests\Unit;

use App\Services\SynapCores\DTOs\ExperimentConfig;
use App\Services\SynapCores\DTOs\PredictionResult;
use App\Services\SynapCores\FakeSynapCoresClient;
use App\Services\SynapCores\SynapCoresService;
use PHPUnit\Framework\TestCase;

class SynapCoresServiceTest extends TestCase
{
    private SynapCoresService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SynapCoresService(new FakeSynapCoresClient());
    }

    public function test_create_experiment_returns_job_id(): void
    {
        $config = new ExperimentConfig(
            name:         'test-experiment',
            targetColumn: 'priority',
            modelType:    'classification',
            features:     ['category', 'customer_tier', 'response_time_expectation'],
        );

        $jobId = $this->service->createExperiment($config);

        $this->assertIsString($jobId);
        $this->assertNotEmpty($jobId);
    }

    public function test_wait_for_training_returns_model_id(): void
    {
        $modelId = $this->service->waitForTraining('fake_job_001');

        $this->assertIsString($modelId);
        $this->assertEquals('fake_model_001', $modelId);
    }

    public function test_predict_returns_prediction_result(): void
    {
        $result = $this->service->predict('fake_model_001', [
            'category'                  => 'outage',
            'customer_tier'             => 'enterprise',
            'response_time_expectation' => 1,
        ]);

        $this->assertInstanceOf(PredictionResult::class, $result);
        $this->assertEquals('critical', $result->predictedClass);
        $this->assertGreaterThan(0, $result->confidence);
        $this->assertLessThanOrEqual(1, $result->confidence);
    }

    public function test_predict_low_priority_ticket(): void
    {
        $result = $this->service->predict('fake_model_001', [
            'category'                  => 'general',
            'customer_tier'             => 'free',
            'response_time_expectation' => 48,
        ]);

        $this->assertInstanceOf(PredictionResult::class, $result);
        $this->assertEquals('low', $result->predictedClass);
    }

    public function test_predict_returns_confidence_between_zero_and_one(): void
    {
        $result = $this->service->predict('fake_model_001', [
            'category'                  => 'billing',
            'customer_tier'             => 'professional',
            'response_time_expectation' => 4,
        ]);

        $this->assertGreaterThanOrEqual(0, $result->confidence);
        $this->assertLessThanOrEqual(1, $result->confidence);
    }

    public function test_run_automl_predict_returns_array_of_prediction_results(): void
    {
        $dataset = [
            ['category' => 'outage',   'customer_tier' => 'enterprise', 'response_time_expectation' => 1],
            ['category' => 'general',  'customer_tier' => 'free',       'response_time_expectation' => 48],
            ['category' => 'billing',  'customer_tier' => 'starter',    'response_time_expectation' => 6],
        ];

        $results = $this->service->runAutoMlPredict('fake_model_001', $dataset);

        $this->assertCount(3, $results);
        $this->assertContainsOnlyInstancesOf(PredictionResult::class, $results);
    }
}