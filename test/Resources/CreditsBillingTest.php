<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class CreditsBillingTest extends TestCase
{
    public function testBalanceReturnsRawEntity(): void
    {
        $balance = [
            'tenantId' => 't_1',
            'balance' => 420,
            'overageUnits' => 0,
            'overageAmountCents' => 0,
            'hardLimit' => null,
        ];
        [$client] = MockApi::client([MockApi::json(['data' => $balance])]);

        $result = $client->credits->balance();

        self::assertNull($result->error);
        self::assertSame($balance, $result->data);
    }

    public function testPlansUnwrapProps(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['props' => ['id' => 'plan_1', 'slug' => 'pro', 'dailyLimit' => 5000]]],
                'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $client->credits->plans();

        self::assertNull($result->error);
        self::assertSame('plan_1', $result->data['data'][0]['id']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/credits/plans', (string) $request->getUri());
    }

    public function testHistoryAndIterateHistory(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['props' => ['id' => 'tx_1', 'type' => 'emission', 'creditDelta' => -1]]],
                'pagination' => ['limit' => 50, 'hasMore' => false, 'nextCursor' => null],
            ]),
            MockApi::json([
                'data' => [['props' => ['id' => 'tx_2']]],
                'pagination' => ['limit' => 1, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $history = $client->credits->history(limit: 50);

        self::assertNull($history->error);
        self::assertSame('tx_1', $history->data['data'][0]['id']);
        self::assertSame(-1, $history->data['data'][0]['creditDelta']);

        $items = iterator_to_array($client->credits->iterateHistory(['limit' => 1]), false);

        self::assertCount(1, $items);
        self::assertSame('tx_2', $items[0]['id']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('http://localhost:4000/v1/credits/history?limit=1', (string) $request->getUri());
    }

    public function testBillingPlanAndUsage(): void
    {
        $plan = [
            'plan' => ['slug' => 'pro', 'name' => 'Pro', 'monthly_allowance' => 1000],
            'subscription' => ['status' => 'active', 'blocked' => false],
            'usage' => ['credits_used' => 12],
        ];
        $usage = [
            'canEmit' => true,
            'creditsRemaining' => 88,
            'creditsUsed' => 12,
            'creditsReserved' => 0,
            'monthlyAllowance' => 100,
            'dailyUsage' => 4,
            'dailyLimit' => 20,
            'activeCompanies' => 2,
            'maxActiveCompanies' => 10,
            'periodStart' => '2026-10-01',
            'periodEnd' => '2026-10-31',
        ];
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => $plan]),
            MockApi::json(['data' => $usage]),
        ]);

        $planResult = $client->billing->plan();
        $planRequest = $mock->getLastRequest();
        $usageResult = $client->billing->usage();
        $usageRequest = $mock->getLastRequest();

        self::assertNull($planResult->error);
        self::assertSame($plan, $planResult->data);
        self::assertNotNull($planRequest);
        self::assertSame('http://localhost:4000/v1/billing/plan', (string) $planRequest->getUri());

        self::assertNull($usageResult->error);
        self::assertTrue($usageResult->data['canEmit']);
        self::assertNotNull($usageRequest);
        self::assertSame('http://localhost:4000/v1/billing/usage', (string) $usageRequest->getUri());
    }
}
