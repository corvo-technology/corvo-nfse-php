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
}
