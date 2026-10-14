<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\DownloadedFile;
use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class InvoicesTest extends TestCase
{
    private const INVOICE = [
        'id' => 'inv_1',
        'companyId' => 'c_1',
        'tenantId' => 't_1',
        'environment' => 'test',
        'documentKeyVersion' => 1,
        'emailSnapshot' => ['send' => true],
        'externalId' => 'pedido_42',
        'idempotencyKey' => 'pedido_42',
        'status' => 'authorized',
        'amount' => 150.5,
        'currency' => 'BRL',
        'competenceDate' => '2026-10-12',
        'nfseNumber' => 12345,
        'stage' => 'documents_ready',
        'createdAt' => '2026-10-12T13:04:38.985Z',
        'updatedAt' => '2026-10-12T13:04:40.230Z',
    ];

    public function testCreateSendsIdempotencyKeyAndUnwrapsProps(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['props' => self::INVOICE]], 201),
        ]);

        $result = $client->invoices->create(
            [
                'companyId' => 'c_1',
                'customer' => ['name' => 'Cliente', 'taxId' => '52998224725'],
                'service' => ['code' => '010101', 'municipalityCode' => '3170206'],
                'amount' => 150.5,
                'competenceDate' => '2026-10-12',
            ],
            idempotencyKey: 'pedido_42',
        );

        self::assertNull($result->error);
        self::assertSame('inv_1', $result->data['id']);
        self::assertSame('authorized', $result->data['status']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/invoices', (string) $request->getUri());
        self::assertSame('pedido_42', $request->getHeaderLine('Idempotency-Key'));

        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('2026-10-12', $body['competenceDate']);
        self::assertSame('52998224725', $body['customer']['taxId']);
    }

    public function testGetUnwrapsProps(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['props' => self::INVOICE]]),
        ]);

        $result = $client->invoices->get('inv_1');

        self::assertNull($result->error);
        self::assertSame(12345, $result->data['nfseNumber']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv_1', (string) $request->getUri());
    }

    public function testListUnwrapsItemPropsAndPassesQueryThrough(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [
                    ['props' => ['id' => 'inv_1', 'status' => 'authorized']],
                    ['props' => ['id' => 'inv_2', 'status' => 'rejected']],
                ],
                'pagination' => ['limit' => 50, 'hasMore' => true, 'nextCursor' => 'cur_2'],
            ]),
        ]);

        $result = $client->invoices->list(['companyId' => 'c_1', 'status' => 'authorized', 'limit' => 50]);

        self::assertNull($result->error);
        self::assertSame('inv_1', $result->data['data'][0]['id']);
        self::assertSame('rejected', $result->data['data'][1]['status']);
        self::assertTrue($result->data['pagination']['hasMore']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame(
            'http://localhost:4000/v1/invoices?companyId=c_1&status=authorized&limit=50',
            (string) $request->getUri(),
        );
    }

    public function testEventsReturnsPaginatedHistory(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [
                    ['props' => ['id' => 'evt_1', 'toStatus' => 'authorized', 'reason' => null]],
                ],
                'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $client->invoices->events('inv_1');

        self::assertNull($result->error);
        self::assertSame('authorized', $result->data['data'][0]['toStatus']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv_1/events', (string) $request->getUri());
    }

    public function testPdfAndXmlReturnRawBytes(): void
    {
        [$client, $mock] = MockApi::client([
            new \GuzzleHttp\Psr7\Response(
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="nfse-12345.pdf"',
                ],
                '%PDF-1.7 nfse',
            ),
            new \GuzzleHttp\Psr7\Response(
                200,
                [
                    'Content-Type' => 'application/xml',
                    'Content-Disposition' => 'attachment; filename="nfse-12345.xml"',
                ],
                '<NFe>xml</NFe>',
            ),
        ]);

        $pdf = $client->invoices->pdf('inv_1');
        $xml = $client->invoices->xml('inv_1');

        self::assertInstanceOf(DownloadedFile::class, $pdf->data);
        self::assertSame('%PDF-1.7 nfse', $pdf->data->content);
        self::assertSame('application/pdf', $pdf->data->mimeType);
        self::assertSame('nfse-12345.pdf', $pdf->data->fileName);

        self::assertNull($xml->error);
        self::assertSame('<NFe>xml</NFe>', $xml->data->content);
        self::assertSame('nfse-12345.xml', $xml->data->fileName);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv_1/xml', (string) $request->getUri());
    }

    public function testDownloadUrlPostsFormat(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['downloadUrl' => 'https://cdn.corvo.tech/nfse.pdf', 'expiresAt' => '2026-10-13T00:00:00Z', 'fileName' => 'nfse.pdf', 'mimeType' => 'application/pdf']]),
        ]);

        $result = $client->invoices->downloadUrl('inv_1', 'pdf');

        self::assertNull($result->error);
        self::assertSame('https://cdn.corvo.tech/nfse.pdf', $result->data['downloadUrl']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv_1/download-url', (string) $request->getUri());
        self::assertSame(['format' => 'pdf'], json_decode((string) $request->getBody(), true));
    }

    public function testSendEmailSendsIdempotencyKey(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['emailId' => 'em_1', 'status' => 'queued']]),
        ]);

        $result = $client->invoices->sendEmail('inv_1', idempotencyKey: 'mail_1');

        self::assertNull($result->error);
        self::assertSame('em_1', $result->data['emailId']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/invoices/inv_1/email', (string) $request->getUri());
        self::assertSame('mail_1', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('', $request->getHeaderLine('Content-Type'));
    }
}
