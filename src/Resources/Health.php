<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Public health checks (no API key required at the server, no data envelope).
 */
final class Health
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/health/live — raw body, no `{data}` envelope.
     *
     * @return Result<mixed>
     */
    public function live(): Result
    {
        return $this->http->request('GET', '/health/live', unwrap: Transport::UNWRAP_NONE);
    }

    /**
     * GET /v1/health/ready — raw body, no `{data}` envelope.
     *
     * @return Result<mixed>
     */
    public function ready(): Result
    {
        return $this->http->request('GET', '/health/ready', unwrap: Transport::UNWRAP_NONE);
    }
}
