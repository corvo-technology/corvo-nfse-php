<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Billing: plan/subscription snapshot and live usage quota.
 */
final class Billing
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/billing/plan — plan, subscription and usage snapshot.
     */
    public function plan(): Result
    {
        return $this->http->request('GET', '/billing/plan');
    }

    /**
     * GET /v1/billing/usage — current quota status.
     */
    public function usage(): Result
    {
        return $this->http->request('GET', '/billing/usage');
    }
}
