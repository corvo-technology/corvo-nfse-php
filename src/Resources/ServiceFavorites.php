<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Favorite services of a company (one-click description, code and amount).
 */
final class ServiceFavorites
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{companyId}/service-favorites — `data` is a plain list (no pagination).
     *
     * @return Result<mixed> list of `ServiceFavorite`.
     */
    public function list(string $companyId): Result
    {
        return $this->http->request('GET', self::path($companyId));
    }

    /**
     * POST /v1/companies/{companyId}/service-favorites
     *
     * @param array{label: string, description: string, serviceCode: string, municipalityCode?: string|null, amountCents?: int|null} $params
     *
     * @return Result<mixed> `ServiceFavorite`.
     */
    public function create(string $companyId, array $params): Result
    {
        return $this->http->request('POST', self::path($companyId), json: $params);
    }

    /**
     * DELETE /v1/companies/{companyId}/service-favorites/{favoriteId} — returns `{deleted: true}`.
     *
     * @return Result<mixed>
     */
    public function remove(string $companyId, string $favoriteId): Result
    {
        return $this->http->request('DELETE', self::path($companyId) . '/' . rawurlencode($favoriteId));
    }

    private static function path(string $companyId): string
    {
        return '/companies/' . rawurlencode($companyId) . '/service-favorites';
    }
}
