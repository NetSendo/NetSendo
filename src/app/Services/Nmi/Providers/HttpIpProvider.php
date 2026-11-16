<?php

namespace App\Services\Nmi\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * What the provider clients share: one authenticated JSON request that turns every
 * failure into an IpProviderException with the provider's own reason in it.
 */
abstract class HttpIpProvider implements IpProvider
{
    protected const TIMEOUT_SECONDS = 20;

    public function __construct(
        protected string $apiKey
    ) {}

    /** Name shown to the person, e.g. "Vultr" */
    abstract public function name(): string;

    /** e.g. "https://api.vultr.com/v2" */
    abstract protected function baseUrl(): string;

    /** The reason inside a provider's error response, null when it has none */
    abstract protected function errorReason(Response $response): ?string;

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(static::TIMEOUT_SECONDS);
    }

    /**
     * @throws IpProviderException
     */
    protected function request(string $method, string $path, array $data = []): Response
    {
        try {
            $response = $this->http()->send($method, ltrim($path, '/'), match (strtoupper($method)) {
                'GET', 'DELETE' => $data === [] ? [] : ['query' => $data],
                default => ['json' => $data === [] ? new \stdClass() : $data],
            });
        } catch (ConnectionException $e) {
            throw new IpProviderException(__('nmi.providers.errors.unreachable', ['provider' => $this->name()]));
        }

        if ($response->successful()) {
            return $response;
        }

        $reason = $this->errorReason($response) ?: 'HTTP ' . $response->status();

        throw new IpProviderException(
            match (true) {
                in_array($response->status(), [401, 403], true) => __('nmi.providers.errors.unauthorized', ['provider' => $this->name(), 'reason' => $reason]),
                default => __('nmi.providers.errors.refused', ['provider' => $this->name(), 'reason' => $reason]),
            },
            $response->status()
        );
    }
}
