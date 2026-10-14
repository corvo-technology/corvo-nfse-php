<?php

declare(strict_types=1);

namespace CorvoNfse;

use CorvoNfse\Resources\Billing;
use CorvoNfse\Resources\Certificates;
use CorvoNfse\Resources\Companies;
use CorvoNfse\Resources\Credits;
use CorvoNfse\Resources\Health;
use CorvoNfse\Resources\Invoices;
use CorvoNfse\Resources\Webhooks;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;

/**
 * Entry point of the Corvo NFS-e SDK.
 *
 * ```php
 * $corvo = new CorvoNfse\Client(['api_key' => 'corvo_live_xxx']);
 * $result = $corvo->companies->list(limit: 10);
 * if ($result->error !== null) {
 *     // $result->error is a CorvoApiError: status, code, requestId, details, retryAfter
 * }
 * ```
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.corvo.tech';

    public readonly Transport $transport;
    public readonly Billing $billing;
    public readonly Certificates $certificates;
    public readonly Companies $companies;
    public readonly Credits $credits;
    public readonly Health $health;
    public readonly Invoices $invoices;
    public readonly Webhooks $webhooks;

    /**
     * @param array{
     *     api_key?: string,
     *     base_url?: string,
     *     prefix?: string,
     *     timeout?: float|int,
     *     allow_insecure_http?: bool,
     *     http_client?: ClientInterface
     * } $options
     *
     * @throws InvalidArgumentException on missing api key or invalid base URL.
     */
    public function __construct(array $options = [])
    {
        $apiKey = isset($options['api_key']) && is_string($options['api_key']) && $options['api_key'] !== ''
            ? $options['api_key']
            : self::env('CORVO_API_KEY');
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'Corvo: api_key is required — pass api_key or set CORVO_API_KEY',
            );
        }

        $baseUrl = isset($options['base_url']) && is_string($options['base_url']) && $options['base_url'] !== ''
            ? $options['base_url']
            : self::env('CORVO_BASE_URL');
        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_BASE_URL;
        }

        $this->transport = new Transport([
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
            'prefix' => $options['prefix'] ?? null,
            'timeout' => $options['timeout'] ?? null,
            'allow_insecure_http' => $options['allow_insecure_http'] ?? false,
            'http_client' => $options['http_client'] ?? null,
        ]);

        $this->billing = new Billing($this->transport);
        $this->certificates = new Certificates($this->transport);
        $this->companies = new Companies($this->transport);
        $this->credits = new Credits($this->transport);
        $this->health = new Health($this->transport);
        $this->invoices = new Invoices($this->transport);
        $this->webhooks = new Webhooks($this->transport);
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? $value : '';
    }
}
