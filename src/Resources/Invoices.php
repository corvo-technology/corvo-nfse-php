<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;
use InvalidArgumentException;

/**
 * NFS-e invoices: issue, read, list, download documents and send emails.
 *
 * Resources come straight in the response `data`; lists add `pagination`.
 */
final class Invoices
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * POST /v1/invoices
     *
     * @param array<string, mixed> $params `InvoiceCreateParams` (wire camelCase). Identify the
     *        company with `companyId` or `companyTaxId`; `customer` is optional (when sent,
     *        `taxId` is required); `service.code`, `service.municipalityCode` and
     *        `service.description` are optional; `options.type` is `IMMEDIATE` or `SCHEDULE`.
     * @param string|null $idempotencyKey safe retry key; sent as `Idempotency-Key`.
     *
     * @return Result<mixed>
     */
    public function create(array $params, ?string $idempotencyKey = null): Result
    {
        return $this->http->request(
            'POST',
            '/invoices',
            json: $params,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * POST /v1/invoices/calculate — estimate the taxes without issuing, storing or charging.
     *
     * Same body as {@see create()}. A fiscal gap is not an error: the result has
     * `status: "blocked"` with `blocking` and `pendingFields`. Money values are decimal strings.
     *
     * @param array<string, mixed> $params `InvoiceCreateParams` (wire camelCase).
     *
     * @return Result<mixed>
     */
    public function calculate(array $params): Result
    {
        return $this->http->request('POST', '/invoices/calculate', json: $params);
    }

    /**
     * GET /v1/invoices/{id}
     *
     * @return Result<mixed>
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', '/invoices/' . rawurlencode($id));
    }

    /**
     * GET /v1/invoices — cursor-paginated.
     *
     * @param array{
     *     companyId?: string,
     *     status?: string,
     *     from?: string,
     *     to?: string,
     *     externalId?: string,
     *     limit?: int,
     *     cursor?: string
     * } $params
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(array $params = []): Result
    {
        return $this->http->list('/invoices', $params, self::plain());
    }

    /**
     * Walk every page of GET /v1/invoices.
     *
     * @param array<string, mixed> $params same shape as {@see list()}.
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/invoices', $params, self::plain());
    }

    /**
     * GET /v1/invoices/{id}/events — status transitions, cursor-paginated.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function events(string $id, array $params = []): Result
    {
        return $this->http->list(
            '/invoices/' . rawurlencode($id) . '/events',
            $params,
            self::plain(),
        );
    }

    /**
     * GET /v1/invoices/{id}/pdf — raw bytes in `Result->data` as a DownloadedFile.
     *
     * @return Result<mixed>
     */
    public function pdf(string $id): Result
    {
        return $this->http->requestRaw('GET', '/invoices/' . rawurlencode($id) . '/pdf');
    }

    /**
     * GET /v1/invoices/{id}/xml — raw bytes in `Result->data` as a DownloadedFile.
     *
     * @return Result<mixed>
     */
    public function xml(string $id): Result
    {
        return $this->http->requestRaw('GET', '/invoices/' . rawurlencode($id) . '/xml');
    }

    /**
     * POST /v1/invoices/{id}/download-url — a short-lived signed URL.
     *
     * @return Result<mixed>
     */
    public function downloadUrl(string $id, string $format): Result
    {
        if ($format !== 'pdf' && $format !== 'xml') {
            throw new InvalidArgumentException('Corvo: format must be "pdf" or "xml", got: ' . $format);
        }

        return $this->http->request(
            'POST',
            '/invoices/' . rawurlencode($id) . '/download-url',
            json: ['format' => $format],
        );
    }

    /**
     * POST /v1/invoices/{id}/email — (re)send the invoice email.
     *
     * @param string|null $idempotencyKey safe retry key; sent as `Idempotency-Key`.
     *
     * @return Result<mixed>
     */
    public function sendEmail(string $id, ?string $idempotencyKey = null): Result
    {
        return $this->http->request(
            'POST',
            '/invoices/' . rawurlencode($id) . '/email',
            idempotencyKey: $idempotencyKey,
        );
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
