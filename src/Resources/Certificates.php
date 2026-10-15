<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * A1 certificates attached to a company (multipart upload).
 */
final class Certificates
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * POST /v1/companies/{companyId}/certificates — multipart upload of a .p12 file.
     *
     * @param string $file raw bytes of the certificate file.
     * @param string $password certificate password.
     *
     * @return Result<mixed>
     */
    public function upload(string $companyId, string $file, string $password, ?string $fileName = null): Result
    {
        return $this->http->requestMultipart(
            'POST',
            '/companies/' . rawurlencode($companyId) . '/certificates',
            [
                [
                    'name' => 'file',
                    'contents' => $file,
                    'filename' => $fileName ?? 'certificate.p12',
                    'headers' => ['Content-Type' => 'application/x-pkcs12'],
                ],
                [
                    'name' => 'password',
                    'contents' => $password,
                ],
            ],
        );
    }

    /**
     * GET /v1/companies/{companyId}/certificates
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(string $companyId, ?int $limit = null, ?string $cursor = null): Result
    {
        return $this->http->list(
            '/companies/' . rawurlencode($companyId) . '/certificates',
            ['limit' => $limit, 'cursor' => $cursor],
            static fn (mixed $item): mixed => $item,
        );
    }

    /**
     * DELETE /v1/companies/{companyId}/certificates/{certId} — 204 yields null data.
     *
     * @return Result<mixed>
     */
    public function remove(string $companyId, string $certId): Result
    {
        return $this->http->request(
            'DELETE',
            '/companies/' . rawurlencode($companyId) . '/certificates/' . rawurlencode($certId),
        );
    }
}
