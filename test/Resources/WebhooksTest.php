<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class WebhooksTest extends TestCase
{
    private const WEBHOOK = [
        'id' => 'wh_1',
        'url' => 'https://exemplo.com/webhook',
        'events' => ['nfse.invoice.authorized'],
        'active' => true,
        'createdAt' => '2026-10-12T13:04:38.985Z',
        'updatedAt' => '2026-10-12T13:04:38.985Z',
    ];

    public function testListReturnsEnvelope(): void
    {
        [$client] = MockApi::client([
            MockApi::json([
                'data' => [self::WEBHOOK],
                'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $client->webhooks->list();

        self::assertNull($result->error);
        self::assertSame('wh_1', $result->data['data'][0]['id']);
        self::assertFalse($result->data['pagination']['hasMore']);
    }

    public function testCreateReturnsSecretExactlyOnce(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => self::WEBHOOK + ['secret' => 'whsec_created']], 201),
        ]);

        $result = $client->webhooks->create([
            'url' => 'https://exemplo.com/webhook',
            'events' => ['nfse.invoice.authorized', 'webhook.test'],
        ]);

        self::assertNull($result->error);
        self::assertSame('whsec_created', $result->data['secret']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/webhooks', (string) $request->getUri());
    }

    public function testTestAndRemoveHitTheExpectedRoutes(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['sent' => true]]),
            MockApi::json(['data' => ['deleted' => true]]),
        ]);

        $sent = $client->webhooks->test('wh_1');
        $testRequest = $mock->getLastRequest();
        $deleted = $client->webhooks->remove('wh_1');
        $deleteRequest = $mock->getLastRequest();

        self::assertNotNull($testRequest);
        self::assertNotNull($deleteRequest);

        self::assertSame('POST', $testRequest->getMethod());
        self::assertSame('http://localhost:4000/v1/webhooks/wh_1/test', (string) $testRequest->getUri());
        self::assertSame('DELETE', $deleteRequest->getMethod());
        self::assertSame('http://localhost:4000/v1/webhooks/wh_1', (string) $deleteRequest->getUri());

        self::assertTrue($sent->data['sent']);
        self::assertTrue($deleted->data['deleted']);
    }

    public function testDeliveriesListAndStats(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['id' => 'dlv_1', 'status' => 'failed']],
                'pagination' => ['limit' => 20, 'hasMore' => false, 'nextCursor' => null],
            ]),
            MockApi::json(['data' => ['total' => 10, 'sent' => 8, 'failed' => 2, 'pending' => 0, 'delayed' => 0]]),
        ]);

        $list = $client->webhooks->deliveries->list(hours: 24, environment: 'test');
        $listRequest = $mock->getLastRequest();
        $stats = $client->webhooks->deliveries->stats(hours: 24, environment: 'test');
        $statsRequest = $mock->getLastRequest();

        self::assertNull($list->error);
        self::assertSame('dlv_1', $list->data['data'][0]['id']);

        self::assertNotNull($listRequest);
        self::assertSame(
            'http://localhost:4000/v1/webhooks/deliveries?hours=24&environment=test',
            (string) $listRequest->getUri(),
        );

        self::assertSame(8, $stats->data['sent']);
        self::assertNotNull($statsRequest);
        self::assertSame(
            'http://localhost:4000/v1/webhooks/deliveries/stats?hours=24&environment=test',
            (string) $statsRequest->getUri(),
        );
    }

    public function testDeliveriesRetryPostsTheRetryRoute(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => ['retried' => true]]),
        ]);

        $result = $client->webhooks->deliveries->retry('dlv_1');

        self::assertTrue($result->data['retried']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/webhooks/deliveries/dlv_1/retry', (string) $request->getUri());
    }

    public function testDeliveriesIterateWalksPages(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['id' => 'dlv_1']],
                'pagination' => ['limit' => 1, 'hasMore' => true, 'nextCursor' => 'next_1'],
            ]),
            MockApi::json([
                'data' => [['id' => 'dlv_2']],
                'pagination' => ['limit' => 1, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $items = iterator_to_array($client->webhooks->deliveries->iterate(['limit' => 1]), false);

        self::assertCount(2, $items);
        self::assertSame('dlv_2', $items[1]['id']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame(
            'http://localhost:4000/v1/webhooks/deliveries?limit=1&cursor=next_1',
            (string) $request->getUri(),
        );
    }
}
