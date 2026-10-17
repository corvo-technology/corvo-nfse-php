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
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(?int $limit = null, ?string $cursor = null): Result
    {
        return $this->http->list('/plans', ['limit' => $limit, 'cursor' => $cursor], static fn (mixed $item): mixed => $item);
    }

    /**
     * GET /v1/plans/{slug} — `free`, `developer`, `growth`, `scale` or `volume`.
     *
     * @return Result<mixed>
     */
    public function get(string $slug): Result
    {
        return $this->http->request('GET', '/plans/' . rawurlencode($slug));
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
