<?php

namespace App\Services\SynapCores;

use App\Services\SynapCores\Contracts\SynapCoresClientInterface;
use App\Services\SynapCores\Exceptions\SynapCoresException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class SynapCoresClient implements SynapCoresClientInterface
{
    private const JWT_CACHE_KEY = 'synapcores_jwt_token';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly int $jwtTtl,
    ) {}

    public function get(string $endpoint): array
    {
        return $this->request('get', $endpoint);
    }

    public function post(string $endpoint, array $payload = []): array
    {
        return $this->request('post', $endpoint, $payload);
    }

    private function request(string $method, string $endpoint, array $payload = [], bool $isRetry = false): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->getToken())
                ->$method("{$this->baseUrl}/{$endpoint}", $payload);

            if ($response->status() === 401 && ! $isRetry) {
                Cache::forget(self::JWT_CACHE_KEY);
                return $this->request($method, $endpoint, $payload, isRetry: true);
            }

            return $this->handleResponse($response);

        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw SynapCoresException::timeout();
        }
    }

    private function getToken(): string
    {
        return Cache::remember(
            self::JWT_CACHE_KEY,
            $this->jwtTtl - 60, // renovar 60s antes de que expire
            fn () => $this->authenticate()
        );
    }

    private function authenticate(): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/v1/auth/login", [
                    'username' => config('services.synapcores.username'),
                    'password' => config('services.synapcores.password'),
                ]);

            if (! $response->successful()) {
                throw SynapCoresException::authenticationFailed($response->body());
            }

            return $response->json('token');

        } catch (SynapCoresException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw SynapCoresException::authenticationFailed($e->getMessage());
        }
    }

    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        throw SynapCoresException::apiError(
            $response->status(),
            $response->json('message') ?? $response->body(),
            $response->json() ?? [],
        );
    }
}