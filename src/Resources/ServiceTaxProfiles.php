<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Service tax profiles: the company's catalog of service classifications.
 */
final class ServiceTaxProfiles
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{id}/service-tax-profiles — the profiles effective on `$date` (plain list).
     *
     * @param string|null $date `YYYY-MM-DD`; defaults to today on the server.
     *
     * @return Result<mixed> list of `ServiceTaxProfile`.
     */
    public function list(string $companyId, ?string $date = null): Result
    {
        return $this->http->request('GET', self::path($companyId), query: ['date' => $date]);
    }

    /**
     * POST /v1/companies/{id}/service-tax-profiles
     *
     * @param array<string, mixed> $params `CreateServiceTaxProfile` (`name`, `serviceCode`, `effectiveFrom` required).
     *
     * @return Result<mixed> `ServiceTaxProfile`.
     */
    public function create(string $companyId, array $params): Result
    {
        return $this->http->request('POST', self::path($companyId), json: $params);
    }

    /**
     * PATCH /v1/companies/{id}/service-tax-profiles/{serviceId}
     *
     * @param array<string, mixed> $params `UpdateServiceTaxProfile` (any subset of the create fields).
     *
     * @return Result<mixed> `ServiceTaxProfile`.
     */
    public function update(string $companyId, string $serviceId, array $params): Result
    {
        return $this->http->request('PATCH', self::path($companyId) . '/' . rawurlencode($serviceId), json: $params);
    }

    /**
     * DELETE /v1/companies/{id}/service-tax-profiles/{serviceId} — only an expired, not approved profile.
     *
     * @return Result<mixed>
     */
    public function remove(string $companyId, string $serviceId): Result
    {
        return $this->http->request('DELETE', self::path($companyId) . '/' . rawurlencode($serviceId));
    }

    /**
     * POST /v1/companies/{id}/service-tax-profiles/{serviceId}/approve
     *
     * @return Result<mixed> `ServiceTaxProfile`.
     */
    public function approve(string $companyId, string $serviceId): Result
    {
        return $this->http->request('POST', self::path($companyId) . '/' . rawurlencode($serviceId) . '/approve');
    }

    private static function path(string $companyId): string
    {
        return '/companies/' . rawurlencode($companyId) . '/service-tax-profiles';
    }
}
