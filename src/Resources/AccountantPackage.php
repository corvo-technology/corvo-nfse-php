<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Monthly package (XML + PDF + summary) sent to the company's accountant.
 */
final class AccountantPackage
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{companyId}/accountant-package
     *
     * @return Result<mixed> `AccountantSettings`: `{email, lastSentMonth}`.
     */
    public function get(string $companyId): Result
    {
        return $this->http->request('GET', self::path($companyId));
    }

    /**
     * PUT /v1/companies/{companyId}/accountant-package — `null` turns the monthly send off.
     * A test e-mail is sent to a new address (`testEmailSent` in the answer).
     *
     * @return Result<mixed> `AccountantSettingsUpdate`.
     */
    public function update(string $companyId, ?string $accountantEmail): Result
    {
        return $this->http->request('PUT', self::path($companyId), json: ['accountantEmail' => $accountantEmail]);
    }

    /**
     * POST /v1/companies/{companyId}/accountant-package/send — send the package of a month now.
     *
     * @param string $month `YYYY-MM`.
     *
     * @return Result<mixed> `AccountantSendResult`: `{status}` (`sent`, `skipped-test`,
     *         `skipped-no-email`, `skipped-empty`, `skipped-too-large`, `skipped-already-sent`).
     */
    public function send(string $companyId, string $month): Result
    {
        return $this->http->request('POST', self::path($companyId) . '/send', json: ['month' => $month]);
    }

    /**
     * GET /v1/companies/{companyId}/accountant-package/sends — last 12 attempts (plain list).
     *
     * @return Result<mixed> list of `AccountantSend`.
     */
    public function listSends(string $companyId): Result
    {
        return $this->http->request('GET', self::path($companyId) . '/sends');
    }

    private static function path(string $companyId): string
    {
        return '/companies/' . rawurlencode($companyId) . '/accountant-package';
    }
}
