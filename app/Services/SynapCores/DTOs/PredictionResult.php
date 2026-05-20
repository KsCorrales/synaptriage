<?php

namespace App\Services\SynapCores\DTOs;

class PredictionResult
{
    public function __construct(
        public readonly string $predictedClass,
        public readonly float $confidence,
        public readonly string $experimentId,
        public readonly ?array $classProbabilities = null,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        return new self(
            predictedClass:      $response['predicted_class'],
            confidence:          (float) $response['confidence'],
            experimentId:        $response['experiment_id'],
            classProbabilities:  $response['class_probabilities'] ?? null,
        );
    }
}