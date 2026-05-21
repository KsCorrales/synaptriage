<?php

namespace App\Services\SynapCores\Contracts;

interface SynapCoresClientInterface
{
    public function get(string $endpoint): array;
    public function post(string $endpoint, array $payload = []): array;
}