<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\DownloadedFile;
use CorvoNfse\CorvoApiError;
use CorvoNfse\Tests\Support\MockApi;
use CorvoNfse\Types\CancellationReason;
use CorvoNfse\Types\Invoice;
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

    public function testCreateSendsIdempotencyKeyAndReturnsTheInvoice(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => self::INVOICE], 201),
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

    public function testGetReturnsTheInvoice(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => self::INVOICE]),
        ]);

        $result = $client->invoices->get('inv_1');

        self::assertNull($result->error);
        self::assertSame(12345, $result->data['nfseNumber']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv_1', (string) $request->getUri());
    }

    public function testListReturnsItemsAndPassesQueryThrough(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [
                    ['id' => 'inv_1', 'status' => 'authorized'],
                    ['id' => 'inv_2', 'status' => 'rejected'],
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
                    ['id' => 'evt_1', 'toStatus' => 'authorized', 'reason' => null],
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

    public function testCancelPostsReasonAndDescriptionAndReturnsTheCancelledInvoice(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => [
                'status' => 'cancelled',
                'cancellationReason' => 'issue_error',
                'cancellationDescription' => 'Valor emitido incorretamente',
                'cancelledAt' => '2026-10-20T10:00:00.000Z',
                'cancellationRequestedAt' => '2026-10-20T10:00:00.000Z',
            ] + self::INVOICE]),
        ]);

        $result = $client->invoices->cancel(
            'inv_1',
            CancellationReason::IssueError,
            'Valor emitido incorretamente',
        );

        self::assertNull($result->error);
        self::assertSame('cancelled', $result->data['status']);
        self::assertSame('2026-10-20T10:00:00.000Z', $result->data['cancelledAt']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/invoices/inv_1/cancel', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(
            ['reason' => 'issue_error', 'description' => 'Valor emitido incorretamente'],
            json_decode((string) $request->getBody(), true),
        );
    }

    public function testCancelOmitsDescriptionWhenNullAndAcceptsAStringReason(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['status' => 'cancelled', 'cancellationReason' => 'other'] + self::INVOICE]),
        ]);

        $result = $client->invoices->cancel('inv/1', 'other');

        self::assertNull($result->error);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/invoices/inv%2F1/cancel', (string) $request->getUri());
        self::assertSame(['reason' => 'other'], json_decode((string) $request->getBody(), true));
    }

    public function testCancelAcceptedWith202ReturnsTheCancellingInvoice(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['data' => [
                'status' => 'cancelling',
                'cancellationReason' => 'service_not_provided',
                'cancellationDescription' => 'Servico nao foi prestado ao cliente',
                'cancelledAt' => null,
                'cancellationRequestedAt' => '2026-10-20T10:00:00.000Z',
            ] + self::INVOICE], 202),
        ]);

        $result = $client->invoices->cancel(
            'inv_1',
            CancellationReason::ServiceNotProvided,
            'Servico nao foi prestado ao cliente',
        );

        self::assertNull($result->error);
        self::assertSame('cancelling', $result->data['status']);

        $invoice = Invoice::fromArray($result->data);
        self::assertSame('cancelling', $invoice->status);
        self::assertSame('service_not_provided', $invoice->cancellationReason);
        self::assertSame(CancellationReason::ServiceNotProvided, CancellationReason::tryFrom((string) $invoice->cancellationReason));
        self::assertNull($invoice->cancelledAt);
        self::assertSame('2026-10-20T10:00:00.000Z', $invoice->cancellationRequestedAt);
    }

    public function testCancelNotCancellableSurfacesTheApiError(): void
    {
        [$client] = MockApi::client([
            MockApi::json([
                'code' => 'ERROR_INVOICE_NOT_CANCELLABLE',
                'message' => 'Invoice cannot be cancelled in its current status',
                'requestId' => 'req_1',
            ], 409),
        ]);

        $result = $client->invoices->cancel('inv_1', CancellationReason::Other, 'Cancelamento solicitado pelo cliente');

        self::assertNull($result->data);
        self::assertInstanceOf(CorvoApiError::class, $result->error);
        self::assertSame(409, $result->error->status);
        self::assertSame('ERROR_INVOICE_NOT_CANCELLABLE', $result->error->code);
        self::assertSame('req_1', $result->error->requestId);
    }

    public function testCancelInvalidDescriptionSurfacesThe422Error(): void
    {
        [$client] = MockApi::client([
            MockApi::json([
                'code' => 'ERROR_VALIDATION',
                'message' => 'Validation failed',
                'details' => [['field' => 'description', 'code' => 'too_short', 'message' => 'min 15']],
            ], 422),
        ]);

        $result = $client->invoices->cancel('inv_1', CancellationReason::Other, 'curta');

        self::assertInstanceOf(CorvoApiError::class, $result->error);
        self::assertSame(422, $result->error->status);
        self::assertSame('description', $result->error->details[0]['field']);
    }

    public function testGetParsesACancelledInvoice(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['data' => [
                'status' => 'cancelled',
                'cancellationReason' => 'issue_error',
                'cancellationDescription' => 'Valor emitido incorretamente',
                'cancelledAt' => '2026-10-20T10:05:00.000Z',
                'cancellationRequestedAt' => '2026-10-20T10:00:00.000Z',
            ] + self::INVOICE]),
        ]);

        $result = $client->invoices->get('inv_1');

        self::assertNull($result->error);
        $invoice = Invoice::fromArray($result->data);
        self::assertSame('cancelled', $invoice->status);
        self::assertSame('issue_error', $invoice->cancellationReason);
        self::assertSame('Valor emitido incorretamente', $invoice->cancellationDescription);
        self::assertSame('2026-10-20T10:05:00.000Z', $invoice->cancelledAt);
        self::assertSame('2026-10-20T10:00:00.000Z', $invoice->cancellationRequestedAt);
    }

    public function testGetParsesACancellingInvoice(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['data' => [
                'status' => 'cancelling',
                'cancellationReason' => 'other',
                'cancellationDescription' => 'Cancelamento solicitado pelo cliente',
                'cancelledAt' => null,
                'cancellationRequestedAt' => '2026-10-20T10:00:00.000Z',
            ] + self::INVOICE]),
        ]);

        $result = $client->invoices->get('inv_1');

        self::assertNull($result->error);
        $invoice = Invoice::fromArray($result->data);
        self::assertSame('cancelling', $invoice->status);
        self::assertSame('other', $invoice->cancellationReason);
        self::assertSame('Cancelamento solicitado pelo cliente', $invoice->cancellationDescription);
        self::assertNull($invoice->cancelledAt);
        self::assertSame('2026-10-20T10:00:00.000Z', $invoice->cancellationRequestedAt);
    }

    public function testInvoiceWithoutCancellationFieldsDefaultsThemToNull(): void
    {
        $invoice = Invoice::fromArray(self::INVOICE);

        self::assertSame('authorized', $invoice->status);
        self::assertNull($invoice->cancellationReason);
        self::assertNull($invoice->cancellationDescription);
        self::assertNull($invoice->cancelledAt);
        self::assertNull($invoice->cancellationRequestedAt);
    }

    public function testCancellationReasonValuesMatchTheApi(): void
    {
        self::assertSame(
            ['issue_error', 'service_not_provided', 'other'],
            array_map(static fn (CancellationReason $r): string => $r->value, CancellationReason::cases()),
        );
    }
}
