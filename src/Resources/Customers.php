<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Saved customers (taker address book), most recently used first.
 */
final class Customers
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/customers — `{data, pagination}`. Here `nextCursor` is the offset of the next
     * page: pass it back untouched as `cursor`.
     *
     * @param array{search?: string, limit?: int, cursor?: string} $params `search` matches name or tax id.
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function search(array $params = []): Result
    {
        return $this->http->list('/customers', $params, self::plain());
    }

    /**
     * Walk every page of GET /v1/customers.
     *
     * @param array{search?: string, limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/customers', $params, self::plain());
    }

    /**
     * GET /v1/customers/{taxId} — CPF or CNPJ, digits.
     *
     * @return Result<mixed> `SavedCustomer`.
     */
    public function get(string $taxId): Result
    {
        return $this->http->request('GET', '/customers/' . rawurlencode($taxId));
    }

    /**
     * PATCH /v1/customers/{taxId} — correct the name or the email (`email: null` clears it).
     *
     * @param array{name?: string, email?: string|null} $params
     *
     * @return Result<mixed> `SavedCustomer`.
     */
    public function update(string $taxId, array $params): Result
    {
        return $this->http->request('PATCH', '/customers/' . rawurlencode($taxId), json: $params);
    }

    /**
     * DELETE /v1/customers/{taxId} — forget a saved customer (issued invoices are not changed).
     *
     * @return Result<mixed>
     */
    public function remove(string $taxId): Result
    {
        return $this->http->request('DELETE', '/customers/' . rawurlencode($taxId));
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
