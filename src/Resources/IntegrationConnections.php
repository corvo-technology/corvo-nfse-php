<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Connections between a company and a payment platform. A connection created with a
 * test key is test-only: it never reaches live data.
 */
final class IntegrationConnections
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/integrations/connections — plain list.
     *
     * @return Result<mixed> list of `IntegrationConnection`.
     */
    public function list(): Result
    {
        return $this->http->request('GET', '/integrations/connections');
    }

    /**
     * POST /v1/integrations/connections — the answer carries `secrets` exactly once.
     *
     * @param array{provider: string, companyId: string, credentials: array<string, mixed>, environment?: string, activeFrom?: string} $params
     *
     * @return Result<mixed> `IntegrationConnectionCreated`: the connection plus `secrets`.
     */
    public function create(array $params): Result
    {
        return $this->http->request('POST', '/integrations/connections', json: $params);
    }

    /**
     * GET /v1/integrations/connections/{id}
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', self::path($id));
    }

    /**
     * POST /v1/integrations/connections/{id}/validate — check the credentials and identify the external account.
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function validate(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/validate');
    }

    /**
     * PUT /v1/integrations/connections/{id}/credentials — replace credentials (for example a rotated key).
     *
     * @param array<string, mixed> $credentials
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function updateCredentials(string $id, array $credentials): Result
    {
        return $this->http->request('PUT', self::path($id) . '/credentials', json: ['credentials' => $credentials]);
    }

    /**
     * POST /v1/integrations/connections/{id}/activate — start issuing invoices automatically.
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function activate(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/activate');
    }

    /**
     * POST /v1/integrations/connections/{id}/pause — stop issuing; events are still recorded.
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function pause(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/pause');
    }

    /**
     * POST /v1/integrations/connections/{id}/disconnect — for good.
     *
     * @return Result<mixed> `IntegrationConnection`.
     */
    public function disconnect(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/disconnect');
    }

    /**
     * GET /v1/integrations/connections/{id}/rules — plain list.
     *
     * @return Result<mixed> list of `IntegrationRule`.
     */
    public function listRules(string $id): Result
    {
        return $this->http->request('GET', self::path($id) . '/rules');
    }

    /**
     * PUT /v1/integrations/connections/{id}/rules — replace every rule.
     *
     * @param list<array<string, mixed>> $rules `IntegrationRuleInput` items (`sendEmail` and `service` required).
     *
     * @return Result<mixed> list of `IntegrationRule`.
     */
    public function replaceRules(string $id, array $rules): Result
    {
        return $this->http->request('PUT', self::path($id) . '/rules', json: ['rules' => $rules]);
    }

    /**
     * GET /v1/integrations/connections/{id}/events — recent events received (plain list).
     *
     * @return Result<mixed> list of `IntegrationEvent`.
     */
    public function listEvents(string $id, ?int $limit = null): Result
    {
        return $this->http->request('GET', self::path($id) . '/events', query: ['limit' => $limit]);
    }

    /**
     * GET /v1/integrations/connections/{id}/operations — recent operations (sales) and their invoice status.
     *
     * @return Result<mixed> list of `IntegrationOperation`.
     */
    public function listOperations(string $id, ?int $limit = null): Result
    {
        return $this->http->request('GET', self::path($id) . '/operations', query: ['limit' => $limit]);
    }

    private static function path(string $id): string
    {
        return '/integrations/connections/' . rawurlencode($id);
    }
}
