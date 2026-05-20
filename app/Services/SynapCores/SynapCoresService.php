<?php

namespace App\Services\SynapCores;

use App\Services\SynapCores\DTOs\ExperimentConfig;
use App\Services\SynapCores\DTOs\PredictionResult;

class SynapCoresService
{
    public function __construct(
        private readonly SynapCoresClient $client,
    ) {}

    public function createExperiment(ExperimentConfig $config): string
    {
        $response = $this->client->post('experiments', $config->toArray());

        return $response['experiment_id'];
    }

    public function train(string $experimentId): void
    {
        $this->client->post("experiments/{$experimentId}/train");
    }

    public function predict(string $experimentId, array $features): PredictionResult
    {
        $response = $this->client->post("experiments/{$experimentId}/predict", [
            'features' => $features,
        ]);

        return PredictionResult::fromApiResponse($response);
    }

    public function runAutoMlPredict(string $experimentId, array $dataset): array
    {
        $response = $this->client->post("experiments/{$experimentId}/automl/predict", [
            'dataset' => $dataset,
        ]);

        return array_map(
            fn (array $result) => PredictionResult::fromApiResponse($result),
            $response['predictions']
        );
    }
}