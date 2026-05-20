<?php

namespace App\Services\SynapCores\Exceptions;

use RuntimeException;

class SynapCoresException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?array $context = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public static function authenticationFailed(string $reason): self
    {
        return new self("SynapCores authentication failed: {$reason}", 401);
    }

    public static function timeout(): self
    {
        return new self('SynapCores request timed out', 408);
    }

    public static function apiError(int $statusCode, string $message, array $context = []): self
    {
        return new self("SynapCores API error: {$message}", $statusCode, $context);
    }
}