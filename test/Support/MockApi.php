<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Support;

use CorvoNfse\Client;
use CorvoNfse\Transport;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Test factory: builds a Client/Transport wired to a Guzzle MockHandler queue.
 */
final class MockApi
{
    public const API_KEY = 'corvo_test_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    public const BASE_URL = 'http://localhost:4000';

    /**
     * @param list<mixed> $responses queued responses, exceptions or promises.
     * @param array<string, mixed> $clientOptions overrides (base_url, api_key, ...).
     *
     * @return array{Client, MockHandler}
     */
    public static function client(array $responses, array $clientOptions = []): array
    {
        $mock = new MockHandler($responses);
        $client = new Client(array_merge(self::defaults($mock), $clientOptions));

        return [$client, $mock];
    }

    /**
     * @param list<mixed> $responses queued responses, exceptions or promises.
     * @param array<string, mixed> $transportOptions overrides.
     *
     * @return array{Transport, MockHandler}
     */
    public static function transport(array $responses, array $transportOptions = []): array
    {
        $mock = new MockHandler($responses);
        $transport = new Transport(array_merge(self::defaults($mock), $transportOptions));

        return [$transport, $mock];
    }

    /**
     * @param MockHandler $mock
     *
     * @return array<string, mixed>
     */
    private static function defaults(MockHandler $mock): array
    {
        return [
            'api_key' => self::API_KEY,
            'base_url' => self::BASE_URL,
            'http_client' => new GuzzleClient([
                'handler' => HandlerStack::create($mock),
                'http_errors' => false,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function json(array $body, int $status = 200, array $headers = []): ResponseInterface
    {
        return new Response(
            $status,
            $headers + ['Content-Type' => 'application/json'],
            (string) json_encode($body, JSON_UNESCAPED_SLASHES),
        );
    }
}
