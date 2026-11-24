<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use CorvoNfse\Types\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * Behavior added or changed by contract 0.3.0 (request shape is covered by OperationsTest).
 */
final class ApiV030Test extends TestCase
{
    public function testCreateWithScheduleTypeReturnsAScheduleNotAnInvoice(): void
    {
        $schedule = [
            'id' => 's_1', 'company_id' => 'c_1', 'kind' => 'recurring', 'status' => 'active',
            'frequency' => 'MONTHLY', 'anchor_on' => '2026-12-01', 'ends_on' => null, 'next_run_on' => '2026-12-01',
            'last_run_on' => null, 'attempts' => 0, 'pause_reason' => null, 'created_at' => '2026-11-24T10:00:00Z',
            'invoice' => ['amount' => 100],
        ];
        [$client, $mock] = MockApi::client([MockApi::json(['data' => $schedule], 202)]);

        $result = $client->invoices->create([
            'companyTaxId' => '11222333000181',
            'service' => ['description' => 'Mensalidade'],
            'amount' => 100,
            'competenceDate' => '2026-12-01',
            'options' => ['type' => 'RECURRING', 'recurrence' => ['frequency' => 'MONTHLY', 'endsOn' => '2027-12-01']],
        ]);

        self::assertNull($result->error);
        self::assertIsArray($result->data);
        self::assertTrue(Schedule::isSchedule($result->data));
        $typed = Schedule::fromArray($result->data);
        self::assertSame('recurring', $typed->kind);
        self::assertSame('2026-12-01', $typed->next_run_on);
        self::assertNull($typed->last_run_on);

        $body = json_decode((string) $mock->getLastRequest()?->getBody(), true);
        self::assertSame('RECURRING', $body['options']['type']);
        self::assertSame('MONTHLY', $body['options']['recurrence']['frequency']);
    }

    public function testImmediateInvoiceIsNotASchedule(): void
    {
        self::assertFalse(Schedule::isSchedule(['id' => 'inv_1', 'status' => 'queued', 'tenantId' => 't_1']));
    }

    public function testCustomersSearchPaginatesWithTheOffsetCursor(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['taxId' => '52998224725', 'name' => 'Ana']],
                'pagination' => ['limit' => 1, 'hasMore' => true, 'nextCursor' => '1'],
            ]),
            MockApi::json([
                'data' => [['taxId' => '11144477735', 'name' => 'Bia']],
                'pagination' => ['limit' => 1, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $names = [];
        foreach ($client->customers->iterate(['search' => 'a', 'limit' => 1]) as $customer) {
            $names[] = $customer['name'];
        }

        self::assertSame(['Ana', 'Bia'], $names);
        parse_str($mock->getLastRequest()?->getUri()->getQuery() ?? '', $query);
        self::assertSame('1', $query['cursor']);
        self::assertSame('a', $query['search']);
    }

    public function testImportsListAndDiscard(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['data' => [['id' => 'i_1', 'status' => 'ready', 'company_id' => 'c_1', 'counters' => ['total' => 3]]]]),
            new \GuzzleHttp\Psr7\Response(204),
        ]);

        $list = $client->imports->list();
        self::assertNull($list->error);
        self::assertSame('i_1', $list->data[0]['id']);

        $discard = $client->imports->discard('i_1');
        self::assertNull($discard->error);
        self::assertNull($discard->data);
    }

    public function testImportEmitWithoutOptionSendsNoBody(): void
    {
        [$client, $mock] = MockApi::client([MockApi::json(['data' => ['id' => 'i_1', 'status' => 'emitting']], 202)]);

        $client->imports->emit('i_1');

        self::assertSame('', (string) $mock->getLastRequest()?->getBody());
    }

    public function testBillingPlanWithoutAPlanIsAnErrorWithItsCode(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['statusCode' => 404, 'code' => 'NO_PLAN_ASSIGNED', 'message' => 'No plan', 'requestId' => 'r1'], 404),
        ]);

        $result = $client->billing->plan();

        self::assertNull($result->data);
        self::assertNotNull($result->error);
        self::assertSame(404, $result->error->status);
        self::assertSame('NO_PLAN_ASSIGNED', $result->error->code);
        self::assertSame('r1', $result->error->requestId);
    }

    public function testCnpjErrorsKeepTheirCodeAndRetryAfter(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['statusCode' => 503, 'code' => 'CNPJ_LOOKUP_UNAVAILABLE', 'message' => 'x'], 503, ['Retry-After' => '30']),
        ]);

        $result = $client->cnpj->lookup('11222333000181');

        self::assertSame('CNPJ_LOOKUP_UNAVAILABLE', $result->error?->code);
        self::assertSame(30, $result->error?->retryAfter);
    }

    public function testSubstituteSendsTheIdempotencyKeyAndParsesTheNewInvoice(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['id' => 'inv_2', 'status' => 'queued', 'substitutesInvoiceId' => 'inv_1']], 202),
        ]);

        $result = $client->invoices->substitute('inv_1', ['description' => 'Valor corrigido na nota'], 'sub-1');

        self::assertSame('inv_1', $result->data['substitutesInvoiceId']);
        self::assertSame('sub-1', $mock->getLastRequest()?->getHeaderLine('Idempotency-Key'));
    }

    public function testReviewRejectsAnUnknownAction(): void
    {
        [$client] = MockApi::client([]);

        $this->expectException(\InvalidArgumentException::class);
        $client->invoices->review('delete', ['x']);
    }

    public function testInvoicePackageDownloadReturnsTheZipBytes(): void
    {
        [$client] = MockApi::client([
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="pacote-2026-10.zip"'], 'PK-zip'),
        ]);

        $result = $client->invoicePackage->download('c_1', '2026-10');

        self::assertSame('PK-zip', $result->data->content);
        self::assertSame('application/zip', $result->data->mimeType);
        self::assertSame('pacote-2026-10.zip', $result->data->fileName);
    }

    public function testImportCsvDownloadsComeBackAsFiles(): void
    {
        [$client] = MockApi::client([
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="modelo.csv"'], "a;b\n"),
        ]);

        $result = $client->imports->downloadTemplate();

        self::assertSame("a;b\n", $result->data->content);
        self::assertSame('modelo.csv', $result->data->fileName);
    }
}
