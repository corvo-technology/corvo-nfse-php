<?php

declare(strict_types=1);

namespace CorvoNfse\Tests;

use CorvoNfse\Client;
use CorvoNfse\Tests\Support\MockApi;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private string|false $savedApiKey;
    private string|false $savedBaseUrl;

    protected function setUp(): void
    {
        $this->savedApiKey = getenv('CORVO_API_KEY');
        $this->savedBaseUrl = getenv('CORVO_BASE_URL');
    }

    protected function tearDown(): void
    {
        self::restoreEnv('CORVO_API_KEY', $this->savedApiKey);
        self::restoreEnv('CORVO_BASE_URL', $this->savedBaseUrl);
    }

    public function testThrowsOnConstructionWithoutApiKey(): void
    {
        putenv('CORVO_API_KEY');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CORVO_API_KEY');

        new Client();
    }

    public function testReadsApiKeyAndBaseUrlFromEnvironment(): void
    {
        putenv('CORVO_API_KEY=corvo_live_' . str_repeat('b', 40));
        putenv('CORVO_BASE_URL=https://instalacao.exemplo.com');

        $mock = new MockHandler([MockApi::json(['status' => 'ok'])]);
        $client = new Client([
            'http_client' => new GuzzleClient([
                'handler' => HandlerStack::create($mock),
                'http_errors' => false,
            ]),
        ]);

        $result = $client->health->live();

        self::assertNull($result->error);
        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://instalacao.exemplo.com/v1/health/live', (string) $request->getUri());
        self::assertSame('corvo_live_' . str_repeat('b', 40), $request->getHeaderLine('X-API-Key'));
    }

    public function testUsesPublicDefaultBaseUrlWhenNothingIsConfigured(): void
    {
        putenv('CORVO_API_KEY');
        putenv('CORVO_BASE_URL');

        $mock = new MockHandler([MockApi::json(['status' => 'ok'])]);
        $client = new Client([
            'api_key' => MockApi::API_KEY,
            'http_client' => new GuzzleClient([
                'handler' => HandlerStack::create($mock),
                'http_errors' => false,
            ]),
        ]);

        $result = $client->health->ready();

        self::assertNull($result->error);
        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://api.corvo.tech/v1/health/ready', (string) $request->getUri());
    }

    public function testHealthReturnsRawBodyWithoutEnvelope(): void
    {
        putenv('CORVO_API_KEY');
        putenv('CORVO_BASE_URL');

        $body = ['status' => 'ok', 'timestamp' => '2026-10-12T16:04:48.268Z', 'uptime' => 10867];
        [$client] = MockApi::client([MockApi::json($body)]);

        $result = $client->health->live();

        self::assertNull($result->error);
        self::assertSame($body, $result->data);
    }

    public function testExposesEveryResourceNamespace(): void
    {
        putenv('CORVO_API_KEY');
        putenv('CORVO_BASE_URL');

        [$client] = MockApi::client([]);

        self::assertInstanceOf(Client::class, $client);
        self::assertNotNull($client->billing);
        self::assertNotNull($client->certificates);
        self::assertNotNull($client->companies);
        self::assertNotNull($client->credits);
        self::assertNotNull($client->health);
        self::assertNotNull($client->invoices);
        self::assertNotNull($client->webhooks);
        self::assertNotNull($client->webhooks->deliveries);
    }

    private static function restoreEnv(string $name, string|false $value): void
    {
        if (is_string($value)) {
            putenv($name . '=' . $value);

            return;
        }
        putenv($name);
    }
}
