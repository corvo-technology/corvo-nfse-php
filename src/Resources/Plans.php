<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Public price catalog (monthly cycle only). Values in centavos.
 */
final class Plans
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/plans — from the smallest to the largest allowance.
     *
     * Public, no API key needed. `$cycle`: `monthly` (default) or `annual` (discounted).
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>} of `PlanPrice`.
     */
    public function list(?int $limit = null, ?string $cursor = null, ?string $cycle = null): Result
    {
        return $this->http->list('/plans', ['limit' => $limit, 'cursor' => $cursor, 'cycle' => $cycle], static fn (mixed $item): mixed => $item);
    }

    /**
     * GET /v1/plans/{slug} — `free`, `solo`, `equipe`, `empresa` or `escala`
     * (`self-hosted` on a self-hosted instance). Treat the slug as an open string.
     *
     * Public, no API key needed. `$cycle`: `monthly` (default) or `annual`.
     *
     * @return Result<mixed> `PlanPrice`.
     */
    public function get(string $slug, ?string $cycle = null): Result
    {
        return $this->http->request('GET', '/plans/' . rawurlencode($slug), query: $cycle !== null ? ['cycle' => $cycle] : []);
    }

    /**
     * GET /v1/plans/quote — the plan recommended for `$volume` invoices per month.
     *
     * `Result->data` is `null` (no error) when the volume is missing or not positive.
     * The keys of the recommendation are snake_case (`recommended_plan`, `total_estimated_cents`).
     *
     * @return Result<mixed>
     */
    public function quote(int $volume): Result
    {
        return $this->http->request('GET', '/plans/quote', query: ['volume' => $volume]);
    }
}
