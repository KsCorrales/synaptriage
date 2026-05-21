<?php

namespace App\Services\SynapCores;

use App\Services\SynapCores\Contracts\SynapCoresClientInterface;

class FakeSynapCoresClient implements SynapCoresClientInterface
{
    public function get(string $endpoint): array
    {
        if (str_contains($endpoint, 'automl/jobs')) {
            return [
                'status'   => 'completed',
                'model_id' => 'fake_model_001',
            ];
        }

        return [];
    }

    public function post(string $endpoint, array $payload = []): array
    {
        if (str_contains($endpoint, 'auth/login')) {
            return [
                'token' => 'fake_jwt_token',
            ];
        }

        if (str_contains($endpoint, 'automl/train')) {
            return [
                'job_id' => 'fake_job_001',
            ];
        }

        if (str_contains($endpoint, 'automl/models') && str_contains($endpoint, 'predict')) {
            $rows = $payload['rows'] ?? [[]];

            return [
                'predictions' => array_map(fn ($row) => [
                    'predicted_class'      => $this->fakePriority($row),
                    'confidence'           => round(mt_rand(70, 99) / 100, 2),
                    'experiment_id'        => 'fake_model_001',
                    'class_probabilities'  => [
                        'low'      => 0.05,
                        'medium'   => 0.10,
                        'high'     => 0.35,
                        'critical' => 0.50,
                    ],
                ], $rows),
            ];
        }

        return [];
    }

    private function fakePriority(array $row): string
    {
        $category = $row['category'] ?? 'general';
        $tier     = $row['customer_tier'] ?? 'free';
        $time     = $row['response_time_expectation'] ?? 24;

        if ($category === 'outage' || $time <= 2) {
            return 'critical';
        }

        if (in_array($category, ['billing', 'technical']) || $tier === 'enterprise') {
            return 'high';
        }

        if ($time <= 8) {
            return 'medium';
        }

        return 'low';
    }
}