<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;
use InvalidArgumentException;

/**
 * NFS-e invoices: issue, read, list, download documents and send emails.
 *
 * Invoice entities are wrapped as `{data: {props: ...}}` by the server; the
 * SDK unwraps them transparently.
 */
final class Invoices
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * POST /v1/invoices
     *
     * @param array<string, mixed> $params `InvoiceCreateParams` (wire camelCase).
     * @param string|null $idempotencyKey safe retry key; sent as `Idempotency-Key`.
     */
    public function create(array $params, ?string $idempotencyKey = null): Result
    {
        return $this->http->request(
            'POST',
            '/invoices',
            json: $params,
            idempotencyKey: $idempotencyKey,
            unwrap: Transport::UNWRAP_DATA_PROPS,
        );
    }

    /**
     * GET /v1/invoices/{id}
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', '/invoices/' . rawurlencode($id), unwrap: Transport::UNWRAP_DATA_PROPS);
    }

    /**
     * GET /v1/invoices — cursor-paginated, items unwrapped from `props`.
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
     * @return Result array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(array $params = []): Result
    {
        return $this->http->list('/invoices', $params, self::mapProps());
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
        return $this->http->iterate('/invoices', $params, self::mapProps());
    }

    /**
     * GET /v1/invoices/{id}/events — status transitions, cursor-paginated.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Result array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function events(string $id, array $params = []): Result
    {
        return $this->http->list(
            '/invoices/' . rawurlencode($id) . '/events',
            $params,
            self::mapProps(),
        );
    }

    /**
     * GET /v1/invoices/{id}/pdf — raw bytes in `Result->data` as a DownloadedFile.
     */
    public function pdf(string $id): Result
    {
        return $this->http->requestRaw('GET', '/invoices/' . rawurlencode($id) . '/pdf');
    }

    /**
     * GET /v1/invoices/{id}/xml — raw bytes in `Result->data` as a DownloadedFile.
     */
    public function xml(string $id): Result
    {
        return $this->http->requestRaw('GET', '/invoices/' . rawurlencode($id) . '/xml');
    }

    /**
     * POST /v1/invoices/{id}/download-url — a short-lived signed URL.
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
     * List items arrive as `{props: {...}}`; unwrap to the entity itself.
     */
    private static function mapProps(): \Closure
    {
        return static function (mixed $item): mixed {
            if (is_array($item) && array_key_exists('props', $item)) {
                return $item['props'];
            }

            return $item;
        };
    }
}
