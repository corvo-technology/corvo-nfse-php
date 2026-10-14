<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class CertificatesTest extends TestCase
{
    private const COMPANY_ID = '09947127-2702-4f6d-9da8-cb463608d812';

    private const CERTIFICATE = [
        'id' => 'cert_1',
        'companyId' => '09947127-2702-4f6d-9da8-cb463608d812',
        'serialNumber' => '33A9FFFF',
        'issuer' => 'AC Certificadora',
        'subject' => 'CNPJ: 41.834.687/0001-48',
        'fingerprint' => 'AB:CD',
        'validFrom' => '2026-01-01T00:00:00.000Z',
        'validUntil' => '2027-01-01T00:00:00.000Z',
        'status' => 'active',
        'createdAt' => '2026-10-12T13:04:38.985Z',
    ];

    public function testUploadSendsMultipartWithFileAndPassword(): void
    {
        [$client, $mock] = MockApi::client([MockApi::json(['data' => self::CERTIFICATE], 201)]);

        $result = $client->certificates->upload(self::COMPANY_ID, "\x30\x82binary", 'segredo');

        self::assertNull($result->error);
        self::assertSame('cert_1', $result->data['id']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'http://localhost:4000/v1/companies/' . self::COMPANY_ID . '/certificates',
            (string) $request->getUri(),
        );

        $contentType = $request->getHeaderLine('Content-Type');
        self::assertStringStartsWith('multipart/form-data; boundary=', $contentType);
        self::assertSame(MockApi::API_KEY, $request->getHeaderLine('X-API-Key'));

        $body = (string) $request->getBody();
        self::assertStringContainsString('filename="certificate.p12"', $body);
        self::assertStringContainsString('application/x-pkcs12', $body);
        self::assertStringContainsString('segredo', $body);
        self::assertStringContainsString('name="password"', $body);
    }

    public function testUploadAcceptsCustomFileName(): void
    {
        [$client, $mock] = MockApi::client([MockApi::json(['data' => self::CERTIFICATE], 201)]);

        $client->certificates->upload(self::COMPANY_ID, 'bytes', 'pw', 'empresa.p12');

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertStringContainsString('filename="empresa.p12"', (string) $request->getBody());
    }

    public function testListReturnsEnvelope(): void
    {
        [$client] = MockApi::client([
            MockApi::json([
                'data' => [self::CERTIFICATE],
                'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $client->certificates->list(self::COMPANY_ID, limit: 20);

        self::assertNull($result->error);
        self::assertSame('cert_1', $result->data['data'][0]['id']);
        self::assertFalse($result->data['pagination']['hasMore']);
    }

    public function testRemoveDeletesAndReturnsNullOn204(): void
    {
        [$client, $mock] = MockApi::client([new \GuzzleHttp\Psr7\Response(204)]);

        $result = $client->certificates->remove(self::COMPANY_ID, 'cert_1');

        self::assertTrue($result->isOk());
        self::assertNull($result->data);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame(
            'http://localhost:4000/v1/companies/' . self::COMPANY_ID . '/certificates/cert_1',
            (string) $request->getUri(),
        );
    }
}
