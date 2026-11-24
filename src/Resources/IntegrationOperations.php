<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Operations (sales) received through a connection.
 */
final class IntegrationOperations
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * PATCH /v1/integrations/operations/{id}/customer — fill in the customer of an operation waiting for it.
     *
     * @param array{taxId: string, name?: string, email?: string} $params
     *
     * @return Result<mixed> `IntegrationOperation`.
     */
    public function completeCustomer(string $id, array $params): Result
    {
        return $this->http->request('PATCH', self::path($id) . '/customer', json: $params);
    }

    /**
     * POST /v1/integrations/operations/{id}/retry — try to issue the invoice again.
     *
     * @return Result<mixed> `IntegrationOperationRetry`: `{state, operationId, lastError}`.
     */
    public function retry(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/retry');
    }

    private static function path(string $id): string
    {
        return '/integrations/operations/' . rawurlencode($id);
    }
}
