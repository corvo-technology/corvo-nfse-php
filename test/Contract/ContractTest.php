<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Contract;

use CorvoNfse\Client;
use PHPUnit\Framework\TestCase;

/**
 * Optional contract test against a live Corvo installation.
 *
 * Skipped unless CORVO_API_KEY and CORVO_BASE_URL are both set, so the
 * default CI run stays hermetic. It exercises exactly the public surface
 * every SDK in the family shares: 2 health checks + authenticated reads.
 */
final class ContractTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $apiKey = getenv('CORVO_API_KEY');
        $baseUrl = getenv('CORVO_BASE_URL');

        if (!is_string($apiKey) || $apiKey === '' || !is_string($baseUrl) || $baseUrl === '') {
            $this->markTestSkipped('CORVO_API_KEY and CORVO_BASE_URL must be set to run contract tests.');
        }

        $this->client = new Client(['api_key' => $apiKey, 'base_url' => $baseUrl]);
    }

    public function testHealthChecksRespond(): void
    {
        $live = $this->client->health->live();
        self::assertNull($live->error, 'health.live failed');
        self::assertIsArray($live->data);

        $ready = $this->client->health->ready();
        self::assertNull($ready->error, 'health.ready failed');
        self::assertIsArray($ready->data);
    }

    public function testAuthenticatedListWorksWithPagination(): void
    {
        $result = $this->client->companies->list(limit: 1);

        self::assertNull($result->error, 'companies.list failed');
        self::assertIsArray($result->data['data']);
        self::assertIsArray($result->data['pagination']);
        self::assertArrayHasKey('hasMore', $result->data['pagination']);
        self::assertArrayHasKey('nextCursor', $result->data['pagination']);
    }

    public function testBillingAndCreditsRead(): void
    {
        $usage = $this->client->billing->usage();
        self::assertNull($usage->error, 'billing.usage failed');
        self::assertIsArray($usage->data);

        $balance = $this->client->credits->balance();
        self::assertNull($balance->error, 'credits.balance failed');
        self::assertIsArray($balance->data);
    }
}
