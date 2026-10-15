<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Companies (Prestadores): create, read, update, soft-delete and paginate.
 */
final class Companies
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * POST /v1/companies
     *
     * @param array<string, mixed> $params `CompanyCreateParams` (wire camelCase).
     *
     * @return Result<mixed>
     */
    public function create(array $params): Result
    {
        return $this->http->request('POST', '/companies', json: $params);
    }

    /**
     * GET /v1/companies
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(?int $limit = null, ?string $cursor = null): Result
    {
        return $this->http->list('/companies', ['limit' => $limit, 'cursor' => $cursor], self::plain());
    }

    /**
     * Walk every page of GET /v1/companies.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/companies', $params, self::plain());
    }

    /**
     * GET /v1/companies/{id}
     *
     * @return Result<mixed>
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', '/companies/' . rawurlencode($id));
    }

    /**
     * PATCH /v1/companies/{id}
     *
     * @param array<string, mixed> $params `CompanyUpdateParams` (wire camelCase).
     *
     * @return Result<mixed>
     */
    public function update(string $id, array $params): Result
    {
        return $this->http->request('PATCH', '/companies/' . rawurlencode($id), json: $params);
    }

    /**
     * DELETE /v1/companies/{id} — 204 yields `new Result(null, null)`.
     *
     * @return Result<mixed>
     */
    public function remove(string $id): Result
    {
        return $this->http->request('DELETE', '/companies/' . rawurlencode($id));
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
