<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Registration data of a CNPJ (company name, address, situation, Simples/MEI).
 */
final class Cnpj
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/cnpj/{cnpj} — digits only (14). Errors use the codes `CNPJ_INVALID`
     * (400/422) and `CNPJ_LOOKUP_UNAVAILABLE` (the registry did not answer).
     *
     * @return Result<mixed> `CnpjProfile`: active, address, legalName, mei, municipalityCode,
     *         simples, situation, tradeName.
     */
    public function lookup(string $cnpj): Result
    {
        return $this->http->request('GET', '/cnpj/' . rawurlencode($cnpj));
    }
}
