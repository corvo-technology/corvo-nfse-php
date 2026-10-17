<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class CalculatePlansTest extends TestCase
{
    public function testCalculateReturnsTheEstimateWithoutIssuing(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => [
                'preEmission' => true,
                'status' => 'ready',
                'taxes' => [
                    'iss' => ['base' => '15000.00', 'rate' => '3', 'amount' => '450.00'],
                    'ibsTotal' => ['base' => '14550.00', 'amount' => '14.55'],
                ],
                'netReceivableAmount' => '15000.00',
                'blocking' => null,
            ]]),
        ]);

        $result = $client->invoices->calculate([
            'companyTaxId' => '11222333000181',
            'service' => (object) [],
            'amount' => 15000,
            'competenceDate' => '2026-10-16',
        ]);

        self::assertNull($result->error);
        self::assertSame('ready', $result->data['status']);
        self::assertSame('450.00', $result->data['taxes']['iss']['amount']);
        self::assertArrayNotHasKey('rate', $result->data['taxes']['ibsTotal']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/invoices/calculate', (string) $request->getUri());
        self::assertArrayNotHasKey('customer', json_decode((string) $request->getBody(), true));
    }

    public function testCalculateBlockedIsDataNotAnError(): void
    {
        [$client] = MockApi::client([
            MockApi::json(['data' => [
                'status' => 'blocked',
                'blocking' => ['code' => 'TAX_RATE_NOT_FOUND', 'message' => 'x', 'step' => 's'],
                'pendingFields' => [['field' => 'iss.rate', 'reason' => 'missing']],
            ]]),
        ]);

        $result = $client->invoices->calculate(['amount' => 10, 'competenceDate' => '2026-10-16', 'service' => (object) []]);

        self::assertNull($result->error);
        self::assertSame('TAX_RATE_NOT_FOUND', $result->data['blocking']['code']);
    }

    public function testPlansCatalogListGetAndQuote(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['slug' => 'developer', 'monthlyAllowance' => 200]],
                'pagination' => ['limit' => 5, 'hasMore' => false, 'nextCursor' => null],
            ]),
            MockApi::json(['data' => ['slug' => 'growth', 'monthlyTotalCents' => 8990]]),
            MockApi::json(['data' => ['recommended_plan' => 'volume', 'total_estimated_cents' => 179990]]),
            MockApi::json(['data' => null]),
        ]);

        $list = $client->plans->list(limit: 5);
        self::assertNull($list->error);
        self::assertSame('developer', $list->data['data'][0]['slug']);
        self::assertSame('http://localhost:4000/v1/plans?limit=5', (string) $mock->getLastRequest()?->getUri());

        $plan = $client->plans->get('growth');
        self::assertSame(8990, $plan->data['monthlyTotalCents']);

        $quote = $client->plans->quote(20000);
        self::assertSame('volume', $quote->data['recommended_plan']);
        self::assertSame('http://localhost:4000/v1/plans/quote?volume=20000', (string) $mock->getLastRequest()?->getUri());

        $none = $client->plans->quote(0);
        self::assertNull($none->error);
        self::assertNull($none->data);
    }
}
