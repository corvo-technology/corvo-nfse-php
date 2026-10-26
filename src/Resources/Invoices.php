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
     *        `discount` is the unconditional discount in BRL (0 up to `amount`); `deduction` is the
     *        ISS base deduction declared by the issuer (greater than 0 and less than `amount` minus
     *        `discount`; the invoice is still issued for the full `amount`).
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
     * POST /v1/invoices/batches — issue 1 to 50 invoices in one call, for one company or several.
     *
     * Items are checked one by one: valid ones are saved together, invalid ones come back
     * `rejected` and are not saved. Match `results[].index` to the item you sent, fix only the
     * rejected ones and send them again. Send an `idempotencyKey` per item: the `Idempotency-Key`
     * header is not used by this route.
     *
     * @param list<array<string, mixed>> $invoices items with the fields of {@see create()} plus an
     *        optional `idempotencyKey` (up to 255 characters).
     *
     * @return Result<mixed> array{total: int, accepted: int, rejected: int, results: list<array{
     *         index: int, status: string, replayed?: true, invoice?: array<string, mixed>,
     *         error?: array{code: string, message: string, details?: mixed}}>}
     */
    public function createBatch(array $invoices): Result
    {
        return $this->http->request('POST', '/invoices/batches', json: ['invoices' => $invoices]);
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

    /**
     * POST /v1/invoices/{id}/cancel
     *
     * A queued invoice is cancelled at once (`status: "cancelled"`); an authorized one moves to
     * `cancelling` while the cancellation is processed, and a processing one stays `processing`
     * with `cancellationRequestedAt` set. Call {@see get()} later: `cancelled` when done, or back
     * to `authorized` with `lastErrorCode` when the cancellation was rejected.
     *
     * @param string $reason `issue_error`, `service_not_provided` or `other`.
     * @param string|null $description 15 to 255 characters; required for an authorized or
     *        processing invoice, optional for a queued one.
     *
     * @return Result<mixed>
     */
    public function cancel(string $id, string $reason, ?string $description = null): Result
    {
        $body = ['reason' => $reason];
        if ($description !== null) {
            $body['description'] = $description;
        }

        return $this->http->request('POST', '/invoices/' . rawurlencode($id) . '/cancel', json: $body);
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
