<?php

namespace App\Services\SynapCores\DTOs;

class ExperimentConfig
{
    public function __construct(
        public readonly string $name,
        public readonly string $targetColumn,
        public readonly string $modelType,
        public readonly array $features,
        public readonly ?string $description = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name'          => $this->name,
            'target_column' => $this->targetColumn,
            'model_type'    => $this->modelType,
            'features'      => $this->features,
            'description'   => $this->description,
        ];
    }
}