<?php

declare(strict_types=1);

namespace CorvoNfse\Tests;

use CorvoNfse\Types\BillingPlanResponse;
use CorvoNfse\Types\Company;
use CorvoNfse\Types\Invoice;
use CorvoNfse\Types\Paginated;
use CorvoNfse\Types\WebhookWithSecret;
use PHPUnit\Framework\TestCase;

final class TypesTest extends TestCase
{
    public function testCompanyFromArrayMirrorsWireFields(): void
    {
        $company = Company::fromArray([
            'id' => 'c_1',
            'tenantId' => 't_1',
            'legalName' => 'Empresa Exemplo LTDA',
            'tradeName' => null,
            'taxId' => '41834687000148',
            'taxRegime' => 'Simples Nacional',
            'simpleNational' => true,
            'addressCity' => 'Uberlândia',
            'addressState' => 'MG',
            'addressZip' => '38400000',
            'fiscalSchemaVersion' => 'v2_02',
            'environment' => 'test',
            'status' => 'ready',
            'createdAt' => '2026-10-12T13:04:38.985Z',
            'updatedAt' => '2026-10-12T13:04:40.230Z',
            'deletedAt' => null,
            'setupGaps' => ['municipalReg'],
            'unknownServerField' => 'ignored',
        ]);

        self::assertSame('c_1', $company->id);
        self::assertNull($company->tradeName);
        self::assertSame('Simples Nacional', $company->taxRegime);
        self::assertTrue($company->simpleNational);
        self::assertSame(['municipalReg'], $company->setupGaps);
        self::assertTrue((new \ReflectionClass($company))->isReadOnly());
    }

    public function testCompanyFromMissingFieldsUsesSafeDefaults(): void
    {
        $company = Company::fromArray(['id' => 'c_2']);

        self::assertSame('c_2', $company->id);
        self::assertSame('', $company->legalName);
        self::assertFalse($company->simpleNational);
        self::assertSame([], $company->setupGaps);
    }

    public function testInvoiceFromArrayKeepsCamelCaseAndSnapshots(): void
    {
        $invoice = Invoice::fromArray([
            'id' => 'inv_1',
            'companyId' => 'c_1',
            'tenantId' => 't_1',
            'environment' => 'test',
            'documentKeyVersion' => 1,
            'emailSnapshot' => ['send' => true],
            'status' => 'authorized',
            'customerSnapshot' => ['name' => 'Cliente'],
            'serviceSnapshot' => ['code' => '010101'],
            'taxSnapshot' => ['iss' => ['rate' => 0.05]],
            'amount' => 150.5,
            'currency' => 'BRL',
            'competenceDate' => '2026-10-12',
            'nfseNumber' => 12345,
            'stage' => 'documents_ready',
            'createdAt' => '2026-10-12T13:04:38.985Z',
            'updatedAt' => '2026-10-12T13:04:40.230Z',
            'documentsStatus' => 'ready',
        ]);

        self::assertSame('inv_1', $invoice->id);
        self::assertSame(1, $invoice->documentKeyVersion);
        self::assertSame(12345, $invoice->nfseNumber);
        self::assertSame(150.5, $invoice->amount);
        self::assertSame(['send' => true], $invoice->emailSnapshot);
        self::assertSame('documents_ready', $invoice->stage);
        self::assertNull($invoice->dpsId);
    }

    public function testWebhookWithSecretFromArray(): void
    {
        $webhook = WebhookWithSecret::fromArray([
            'id' => 'wh_1',
            'url' => 'https://exemplo.com/hook',
            'events' => ['nfse.invoice.authorized', 'webhook.test'],
            'active' => true,
            'createdAt' => '2026-10-12T13:04:38.985Z',
            'updatedAt' => '2026-10-12T13:04:38.985Z',
            'secret' => 'whsec_abc',
        ]);

        self::assertSame('whsec_abc', $webhook->secret);
        self::assertCount(2, $webhook->events);
    }

    public function testPaginatedFromArray(): void
    {
        $page = Paginated::fromArray([
            'data' => [['id' => 'c_1'], ['id' => 'c_2']],
            'pagination' => ['limit' => 2, 'hasMore' => true, 'nextCursor' => 'cur_2'],
        ]);

        self::assertCount(2, $page->data);
        self::assertSame(2, $page->pagination->limit);
        self::assertTrue($page->pagination->hasMore);
        self::assertSame('cur_2', $page->pagination->nextCursor);
    }

    public function testBillingPlanResponseKeepsServerSnakeCase(): void
    {
        $billing = BillingPlanResponse::fromArray([
            'plan' => [
                'slug' => 'pro',
                'name' => 'Pro',
                'monthly_allowance' => 1000,
                'daily_limit' => null,
                'max_active_companies' => 5,
                'monthly_price_cents' => 9900,
            ],
            'subscription' => [
                'status' => 'active',
                'paid_until' => '2026-11-01',
                'cancel_at_period_end' => false,
                'scheduled_plan' => null,
                'blocked' => false,
            ],
            'usage' => ['credits_used' => 12],
        ]);

        self::assertSame(1000, $billing->plan['monthly_allowance']);
        self::assertNull($billing->plan['daily_limit']);
        self::assertFalse($billing->subscription['cancel_at_period_end']);
        self::assertSame(12, $billing->usage['credits_used']);
    }

    public function testCreditBalanceFollowsContract030(): void
    {
        $balance = \CorvoNfse\Types\CreditBalance::fromArray([
            'balance' => 12, 'planRemaining' => 10, 'avulsoBalance' => 2, 'hardLimit' => 500,
        ]);

        self::assertSame(12, $balance->balance);
        self::assertSame(10, $balance->planRemaining);
        self::assertSame(2, $balance->avulsoBalance);
        self::assertSame(500, $balance->hardLimit);
    }

    public function testNewWireTypesMirrorTheContract(): void
    {
        $customer = \CorvoNfse\Types\SavedCustomer::fromArray([
            'taxId' => '52998224725', 'name' => 'Ana', 'email' => null,
            'createdAt' => '2026-11-01T00:00:00Z', 'updatedAt' => '2026-11-02T00:00:00Z', 'lastUsedAt' => '2026-11-03T00:00:00Z',
        ]);
        self::assertSame('Ana', $customer->name);
        self::assertNull($customer->email);

        $preview = \CorvoNfse\Types\PurchasePreview::fromArray(['units' => 10, 'unitPriceCents' => 99, 'totalCents' => 990]);
        self::assertSame(990, $preview->totalCents);

        $run = \CorvoNfse\Types\ScheduleRun::fromArray([
            'id' => 'r1', 'attempt' => 2, 'outcome' => 'failed', 'error_code' => 'ERROR_X', 'error_message' => 'x',
            'invoice_id' => null, 'occurrence_on' => '2026-12-01', 'ran_on' => '2026-12-01', 'created_at' => '2026-12-01T12:00:00Z',
        ]);
        self::assertSame(2, $run->attempt);
        self::assertNull($run->invoice_id);

        $job = \CorvoNfse\Types\ImportJob::fromArray([
            'id' => 'i1', 'status' => 'ready', 'company_id' => null, 'file_name' => 'a.csv', 'created_at' => '2026-11-01T00:00:00Z',
            'counters' => ['total' => 3, 'valid' => 2, 'invalid' => 1], 'unknown_columns' => ['x'],
        ]);
        self::assertSame(3, $job->counters['total']);
        self::assertSame(['x'], $job->unknown_columns);
        self::assertNull($job->company_id);

        $invoice = Invoice::fromArray(['id' => 'i', 'substitutesInvoiceId' => 'old', 'portalFlag' => 'taker_rejected']);
        self::assertSame('old', $invoice->substitutesInvoiceId);
        self::assertSame('taker_rejected', $invoice->portalFlag);

        $event = \CorvoNfse\Types\InvoiceEvent::fromArray(['id' => 'e', 'source' => 'portal', 'portalNsu' => '77']);
        self::assertSame('portal', $event->source);
        self::assertSame('77', $event->portalNsu);
    }

    public function testPackageSummaryTotalIsADecimalString(): void
    {
        $summary = \CorvoNfse\Types\PackageSummary::fromArray(['companyName' => 'A', 'companyTaxId' => '1', 'count' => 2, 'limit' => 10, 'month' => '2026-10', 'total' => '1234.50']);
        self::assertSame('1234.50', $summary->total);
    }

    public function testCompanyNullableFieldsReadAsNull(): void
    {
        $company = Company::fromArray(['id' => 'c', 'tradeName' => null, 'addressStreet' => null, 'municipalReg' => null, 'defaultServiceCode' => null]);
        self::assertNull($company->tradeName);
        self::assertNull($company->addressStreet);
        self::assertNull($company->municipalReg);
        self::assertNull($company->defaultServiceCode);
    }
}
