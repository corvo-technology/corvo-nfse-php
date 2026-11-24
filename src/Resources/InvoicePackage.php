<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Monthly ZIP of a company's authorized invoices (`xml/`, `pdf/` and `resumo.csv`).
 */
final class InvoicePackage
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/companies/{companyId}/invoice-package/summary — count and total, without building the ZIP.
     *
     * @param string $month competence month, `YYYY-MM`.
     *
     * @return Result<mixed> `PackageSummary`: companyName, companyTaxId, count, limit, month, total (`total` is a decimal string such as "1234.50").
     */
    public function summary(string $companyId, string $month): Result
    {
        return $this->http->request(
            'GET',
            '/companies/' . rawurlencode($companyId) . '/invoice-package/summary',
            query: ['month' => $month],
        );
    }

    /**
     * GET /v1/companies/{companyId}/invoice-package — the ZIP bytes as a DownloadedFile in `Result->data`.
     * `PACKAGE_TOO_LARGE` (422) when the month exceeds the limit of the summary.
     *
     * @param string $month competence month, `YYYY-MM`.
     * @param string|null $include comma-separated parts, `xml`, `pdf` (default: both).
     *
     * @return Result<mixed>
     */
    public function download(string $companyId, string $month, ?string $include = null): Result
    {
        return $this->http->requestRaw(
            'GET',
            '/companies/' . rawurlencode($companyId) . '/invoice-package',
            ['month' => $month, 'include' => $include],
        );
    }
}
