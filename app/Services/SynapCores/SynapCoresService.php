<?php

namespace App\Services\SynapCores;

use App\Services\SynapCores\Contracts\SynapCoresClientInterface;
use App\Services\SynapCores\DTOs\ExperimentConfig;
use App\Services\SynapCores\DTOs\PredictionResult;

class SynapCoresService
{
    public function __construct(
        private readonly SynapCoresClientInterface $client,
    ) {}

    public function createExperiment(ExperimentConfig $config): string
    {
        $response = $this->client->post('v1/automl/train', [
            'dataset_id'    => $config->name,
            'task'          => $config->modelType,
            'target_column' => $config->targetColumn,
            'algorithms'    => ['random_forest', 'xgboost', 'neural_network'],
        ]);

        return $response['job_id'];
    }

    public function waitForTraining(string $jobId): string
    {
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $response = $this->client->get("v1/automl/jobs/{$jobId}");

            if ($response['status'] === 'completed') {
                return $response['model_id'];
            }

            if ($response['status'] === 'failed') {
                throw new \App\Services\SynapCores\Exceptions\SynapCoresException(
                    'Training job failed: ' . ($response['error'] ?? 'unknown error')
                );
            }

            sleep(10);
            $attempt++;
        }

        throw new \App\Services\SynapCores\Exceptions\SynapCoresException(
            'Training job timed out after ' . ($maxAttempts * 10) . ' seconds'
        );
    }

    public function predict(string $modelId, array $features): PredictionResult
    {
        $response = $this->client->post("v1/automl/models/{$modelId}/predict", [
            'rows' => [$features],
        ]);

        return PredictionResult::fromApiResponse($response['predictions'][0]);
    }

    public function runAutoMlPredict(string $modelId, array $dataset): array
    {
        $response = $this->client->post("v1/automl/models/{$modelId}/predict", [
            'rows' => $dataset,
        ]);

        return array_map(
            fn (array $result) => PredictionResult::fromApiResponse($result),
            $response['predictions']
        );
    }
}