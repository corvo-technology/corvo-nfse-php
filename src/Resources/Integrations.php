<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Payment platforms Corvo can connect to. See `integrationConnections` and
 * `integrationOperations` for the connections themselves.
 */
final class Integrations
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/integrations/providers — plain list.
     *
     * @return Result<mixed> list of `IntegrationProvider`.
     */
    public function listProviders(): Result
    {
        return $this->http->request('GET', '/integrations/providers');
    }
}
