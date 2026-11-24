<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Fiscal profile history of a company (tax regime and its validity periods).
 */
final class FiscalProfiles
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{id}/fiscal-profile — the history (plain list).
     *
     * @return Result<mixed> list of `FiscalProfile`.
     */
    public function list(string $companyId): Result
    {
        return $this->http->request('GET', self::path($companyId));
    }

    /**
     * PUT /v1/companies/{id}/fiscal-profile — create a validity period (idempotent for an identical state).
     *
     * @param array<string, mixed> $params `UpsertFiscalProfile`: `taxRegime` (required), `validFrom`,
     *        `simpleNational`, `simplesApuracao`, `specialTaxRegime`, `ibsCbsRegularOption`,
     *        `ibsCbsRegularFrom`, `confirm`.
     *
     * @return Result<mixed> `FiscalProfile`.
     */
    public function upsert(string $companyId, array $params): Result
    {
        return $this->http->request('PUT', self::path($companyId), json: $params);
    }

    /**
     * POST /v1/companies/{id}/fiscal-profile/{profileId}/approve
     *
     * @return Result<mixed> `FiscalProfile`.
     */
    public function approve(string $companyId, string $profileId): Result
    {
        return $this->http->request('POST', self::path($companyId) . '/' . rawurlencode($profileId) . '/approve');
    }

    private static function path(string $companyId): string
    {
        return '/companies/' . rawurlencode($companyId) . '/fiscal-profile';
    }
}
