<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Delivery log of every webhook attempt: list, iterate, stats and retry.
 */
final class Deliveries
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/webhooks/deliveries
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(
        ?int $hours = null,
        ?string $environment = null,
        ?int $limit = null,
        ?string $cursor = null,
    ): Result {
        return $this->http->list(
            '/webhooks/deliveries',
            ['hours' => $hours, 'environment' => $environment, 'limit' => $limit, 'cursor' => $cursor],
            self::plain(),
        );
    }

    /**
     * Walk every page of GET /v1/webhooks/deliveries.
     *
     * @param array{hours?: int, environment?: string, limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/webhooks/deliveries', $params, self::plain());
    }

    /**
     * GET /v1/webhooks/deliveries/stats — counters for the last N hours.
     *
     * @return Result<mixed>
     */
    public function stats(?int $hours = null, ?string $environment = null): Result
    {
        return $this->http->request(
            'GET',
            '/webhooks/deliveries/stats',
            query: ['hours' => $hours, 'environment' => $environment],
        );
    }

    /**
     * POST /v1/webhooks/deliveries/{deliveryId}/retry — returns `{retried: true}`.
     *
     * @return Result<mixed>
     */
    public function retry(string $deliveryId): Result
    {
        return $this->http->request(
            'POST',
            '/webhooks/deliveries/' . rawurlencode($deliveryId) . '/retry',
        );
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
