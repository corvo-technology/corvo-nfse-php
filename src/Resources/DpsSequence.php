<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * DPS numbering of a company: continue it from the last note issued in another system.
 */
final class DpsSequence
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{id}/dps-sequence
     *
     * @return Result<mixed> `DpsSequence`: `{series, lastNumber}`.
     */
    public function get(string $companyId): Result
    {
        return $this->http->request('GET', self::path($companyId));
    }

    /**
     * PUT /v1/companies/{id}/dps-sequence — error `DPS_NUMBER_BELOW_CURRENT` when the number goes back.
     *
     * @param int $lastNumber the last DPS number already used; the next note takes the following one.
     *
     * @return Result<mixed> `DpsSequence`.
     */
    public function set(string $companyId, int $lastNumber): Result
    {
        return $this->http->request('PUT', self::path($companyId), json: ['lastNumber' => $lastNumber]);
    }

    private static function path(string $companyId): string
    {
        return '/companies/' . rawurlencode($companyId) . '/dps-sequence';
    }
}
