<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Webhook endpoints and their delivery attempts.
 *
 * `$webhooks->deliveries` covers the delivery log: list, iterate, stats, retry.
 */
final class Webhooks
{
    public readonly Deliveries $deliveries;

    public function __construct(private readonly Transport $http)
    {
        $this->deliveries = new Deliveries($http);
    }

    /**
     * GET /v1/webhooks
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(array $params = []): Result
    {
        return $this->http->list('/webhooks', $params, self::plain());
    }

    /**
     * Walk every page of GET /v1/webhooks.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/webhooks', $params, self::plain());
    }

    /**
     * POST /v1/webhooks — the response carries the signing `secret` exactly once.
     *
     * @param array{url: string, events: list<string>} $params
     *
     * @return Result<mixed>
     */
    public function create(array $params): Result
    {
        return $this->http->request('POST', '/webhooks', json: $params);
    }

    /**
     * DELETE /v1/webhooks/{id} — returns `{deleted: true}`.
     *
     * @return Result<mixed>
     */
    public function remove(string $id): Result
    {
        return $this->http->request('DELETE', '/webhooks/' . rawurlencode($id));
    }

    /**
     * POST /v1/webhooks/{id}/test — sends a `webhook.test` event; returns `{sent: true}`.
     *
     * @return Result<mixed>
     */
    public function test(string $id): Result
    {
        return $this->http->request('POST', '/webhooks/' . rawurlencode($id) . '/test');
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
