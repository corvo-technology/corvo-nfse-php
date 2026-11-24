<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Status of the national NFS-e portal for the key's environment.
 */
final class FiscalPortal
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/fiscal-portal/status
     *
     * @return Result<mixed> `PortalStatus`: `{available, environment, since, retryAt}`.
     */
    public function status(): Result
    {
        return $this->http->request('GET', '/fiscal-portal/status');
    }
}
