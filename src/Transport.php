<?php

declare(strict_types=1);

namespace CorvoNfse;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Low-level HTTP transport over Guzzle 7 (`http_errors: false`, 30s timeout).
 *
 * Builds URLs with the `v1` prefix, attaches the `X-API-Key` header (never
 * `Authorization: Bearer`), unwraps response envelopes and maps failures to
 * {@see CorvoApiError}. Never throws for API, network or timeout failures.
 */
final class Transport
{
    public const UNWRAP_DATA = 'data';
    public const UNWRAP_DATA_PROPS = 'data+props';
    public const UNWRAP_NONE = 'none';

    private const DEFAULT_TIMEOUT = 30.0;

    private ClientInterface $client;
    private string $apiKey;
    private string $baseUrl;
    private string $prefix;
    private float $timeout;
    private bool $allowInsecureHttp;

    /**
     * @param array{
     *     api_key?: string|null,
     *     base_url?: string|null,
     *     prefix?: string|null,
     *     timeout?: float|int|null,
     *     allow_insecure_http?: bool|null,
     *     http_client?: ClientInterface|null
     * } $options
     */
    public function __construct(array $options)
    {
        $apiKey = isset($options['api_key']) ? (string) $options['api_key'] : '';
        if ($apiKey === '') {
            throw new InvalidArgumentException('Corvo: api_key is required — pass api_key or set CORVO_API_KEY');
        }

        $baseUrl = isset($options['base_url']) ? rtrim((string) $options['base_url'], '/') : '';
        if ($baseUrl === '') {
            throw new InvalidArgumentException('Corvo: invalid baseUrl: (empty)');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->prefix = trim((string) ($options['prefix'] ?? 'v1'), '/');
        $this->timeout = (float) ($options['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->allowInsecureHttp = (bool) ($options['allow_insecure_http'] ?? false);

        $this->validateBaseUrl($baseUrl);

        $this->client = $options['http_client'] ?? new GuzzleClient([
            'http_errors' => false,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Perform a request and unwrap the response envelope.
     *
     * @param array<string, mixed>|null $json JSON request body (sets Content-Type).
     * @param array<string, mixed>|null $query stringifiable query params; nulls are dropped.
     * @param string $unwrap one of the UNWRAP_* constants.
     */
    public function request(
        string $method,
        string $path,
        ?array $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        string $unwrap = self::UNWRAP_DATA,
    ): Result {
        return $this->requestAsync($method, $path, $json, $query, $idempotencyKey, $unwrap)->wait();
    }

    /**
     * Async variant of {@see request()}: resolves with a {@see Result}, never rejects.
     *
     * @return PromiseInterface<Result>
     */
    public function requestAsync(
        string $method,
        string $path,
        ?array $json = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
        string $unwrap = self::UNWRAP_DATA,
    ): PromiseInterface {
        try {
            $promise = $this->client->requestAsync(
                $method,
                $this->buildUrl($path, $query),
                $this->buildOptions($json, $idempotencyKey),
            );
        } catch (\Throwable $throwable) {
            return Create::promiseFor(new Result(null, $this->transportError($throwable)));
        }

        return $promise->then(
            fn (ResponseInterface $response): Result => $this->handleResponse($response, $unwrap),
            fn (mixed $reason): Result => $this->rejectionToResult($reason, $unwrap),
        );
    }

    /**
     * Perform a `multipart/form-data` request (certificate uploads).
     *
     * @param array<int, array<string, mixed>> $multipart Guzzle multipart parts.
     */
    public function requestMultipart(
        string $method,
        string $path,
        array $multipart,
        ?array $query = null,
        string $unwrap = self::UNWRAP_DATA,
    ): Result {
        try {
            $promise = $this->client->requestAsync(
                $method,
                $this->buildUrl($path, $query),
                $this->buildOptions(null, null, $multipart),
            );
        } catch (\Throwable $throwable) {
            return new Result(null, $this->transportError($throwable));
        }

        return $promise->then(
            fn (ResponseInterface $response): Result => $this->handleResponse($response, $unwrap),
            fn (mixed $reason): Result => $this->rejectionToResult($reason, $unwrap),
        )->wait();
    }

    /**
     * Perform a request and return the raw bytes (no envelope unwrapping).
     */
    public function requestRaw(string $method, string $path, ?array $query = null): Result
    {
        try {
            $response = $this->client
                ->requestAsync($method, $this->buildUrl($path, $query), $this->buildOptions(null, null))
                ->wait();
        } catch (RequestException $exception) {
            if ($exception->getResponse() !== null) {
                return new Result(null, $this->toError($exception->getResponse()));
            }

            return new Result(null, $this->transportError($exception));
        } catch (\Throwable $throwable) {
            return new Result(null, $this->transportError($throwable));
        }

        if (!$response instanceof ResponseInterface) {
            return new Result(null, new CorvoApiError(0, 'network_error', 'Network request failed: unexpected response'));
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return new Result(null, $this->toError($response));
        }

        $contentDisposition = $response->getHeaderLine('Content-Disposition');
        $fileName = null;
        if (preg_match('/filename="([^"]+)"/', $contentDisposition, $matches) === 1) {
            $fileName = $matches[1];
        }
        $mimeType = $response->getHeaderLine('Content-Type');

        return new Result(
            new DownloadedFile(
                (string) $response->getBody(),
                $mimeType !== '' ? $mimeType : null,
                $fileName,
            ),
            null,
        );
    }

    /**
     * GET a paginated list and return `{data, pagination}` with each item mapped.
     *
     * @param array<string, mixed>|null $query
     * @param callable(mixed): mixed $mapItem
     */
    public function list(string $path, ?array $query, callable $mapItem): Result
    {
        $result = $this->request('GET', $path, query: $query, unwrap: self::UNWRAP_NONE);
        if ($result->error !== null) {
            return $result;
        }

        $page = is_array($result->data) ? $result->data : [];
        $items = is_array($page['data'] ?? null) ? array_values(array_map($mapItem, $page['data'])) : [];
        $pagination = is_array($page['pagination'] ?? null)
            ? $page['pagination']
            : ['limit' => 0, 'hasMore' => false, 'nextCursor' => null];

        return new Result(['data' => $items, 'pagination' => $pagination], null);
    }

    /**
     * Walk every page of a cursor-paginated list, yielding mapped items.
     *
     * @param array<string, mixed> $query
     * @param callable(mixed): mixed $mapItem
     *
     * @throws CorvoApiError on the FIRST page failure — the only place in the
     *         SDK where an API error is raised instead of returned.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(string $path, array $query, callable $mapItem): \Generator
    {
        $cursor = $query['cursor'] ?? null;

        while (true) {
            $pageQuery = $query;
            $pageQuery['cursor'] = $cursor;

            $page = $this->list($path, $pageQuery, $mapItem);
            if ($page->error !== null) {
                throw $page->error;
            }

            $pageData = is_array($page->data) ? $page->data : [];
            $items = is_array($pageData['data'] ?? null) ? $pageData['data'] : [];
            foreach ($items as $item) {
                yield $item;
            }

            $pagination = is_array($pageData['pagination'] ?? null) ? $pageData['pagination'] : [];
            if (($pagination['hasMore'] ?? false) !== true) {
                return;
            }
            $next = $pagination['nextCursor'] ?? null;
            if ($next === null || $next === '') {
                return;
            }
            $cursor = $next;
        }
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, mixed>|null $query
     */
    private function buildUrl(string $path, ?array $query): string
    {
        $url = $this->baseUrl
            . ($this->prefix !== '' ? '/' . $this->prefix : '')
            . (str_starts_with($path, '/') ? $path : '/' . $path);

        $params = [];
        foreach ($query ?? [] as $key => $value) {
            if ($value === null) {
                continue;
            }
            $params[(string) $key] = $value;
        }

        if ($params !== []) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<int, array<string, mixed>>|null $multipart
     *
     * @return array<string, mixed>
     */
    private function buildOptions(?array $json, ?string $idempotencyKey, ?array $multipart = null): array
    {
        $headers = [
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $options = [
            'headers' => $headers,
            'http_errors' => false,
            'timeout' => $this->timeout,
        ];

        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $options['headers'] = $headers;
            $options['json'] = $json;
        } elseif ($multipart !== null) {
            // Content-Type with the multipart boundary is set by Guzzle.
            $options['multipart'] = $multipart;
        }

        return $options;
    }

    private function handleResponse(ResponseInterface $response, string $unwrap): Result
    {
        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            return new Result(null, $this->toError($response));
        }

        if ($status === 204) {
            return new Result(null, null);
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return new Result(null, null);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Result(
                null,
                new CorvoApiError(
                    $status,
                    'invalid_json',
                    'Invalid JSON in response',
                    requestId: $this->headerValue($response, 'X-Request-Id'),
                ),
            );
        }

        return new Result($this->unwrap($decoded, $unwrap), null);
    }

    private function unwrap(mixed $value, string $mode): mixed
    {
        if ($mode === self::UNWRAP_NONE) {
            return $value;
        }

        $envelope = is_array($value) && array_key_exists('data', $value) ? $value['data'] : null;

        if ($mode === self::UNWRAP_DATA) {
            return $envelope ?? null;
        }

        // UNWRAP_DATA_PROPS: the entity lives under `data.props`.
        if (is_array($envelope) && array_key_exists('props', $envelope)) {
            return $envelope['props'];
        }

        return null;
    }

    private function toError(ResponseInterface $response): CorvoApiError
    {
        $status = $response->getStatusCode();

        $decoded = json_decode((string) $response->getBody(), true);
        $record = is_array($decoded) ? $decoded : [];

        $code = 'http_' . $status;
        if (is_string($record['code'] ?? null) && $record['code'] !== '') {
            $code = $record['code'];
        }

        $message = 'Request failed with status ' . $status;
        if (is_string($record['message'] ?? null)) {
            $message = $record['message'];
        } elseif (is_array($record['message'] ?? null)) {
            $parts = array_map(
                static fn (mixed $part): string => is_scalar($part)
                    ? (string) $part
                    : (string) json_encode($part),
                $record['message'],
            );
            $message = implode('; ', $parts);
        } elseif (is_string($record['error'] ?? null)) {
            $message = $record['error'];
        }

        $details = is_array($record['details'] ?? null) ? $record['details'] : [];

        $requestId = is_string($record['requestId'] ?? null) ? $record['requestId'] : null;
        if ($requestId === null) {
            $requestId = $this->headerValue($response, 'X-Request-Id');
        }

        $retryAfter = null;
        if (is_int($record['retryAfter'] ?? null)) {
            $retryAfter = $record['retryAfter'];
        } elseif (is_float($record['retryAfter'] ?? null)) {
            $retryAfter = (int) $record['retryAfter'];
        } else {
            $retryAfterHeader = $this->headerValue($response, 'Retry-After');
            if ($retryAfterHeader !== null && is_numeric($retryAfterHeader)) {
                $retryAfter = (int) $retryAfterHeader;
            }
        }

        return new CorvoApiError(
            $status,
            $code,
            $message,
            $details,
            $requestId,
            $retryAfter,
        );
    }

    private function rejectionToResult(mixed $reason, string $unwrap): Result
    {
        if ($reason instanceof RequestException && $reason->getResponse() !== null) {
            return new Result(null, $this->toError($reason->getResponse()));
        }
        if ($reason instanceof ResponseInterface) {
            return $this->handleResponse($reason, $unwrap);
        }

        return new Result(null, $this->transportError($reason));
    }

    private function transportError(mixed $reason): CorvoApiError
    {
        $message = $reason instanceof \Throwable
            ? $reason->getMessage()
            : (is_scalar($reason) ? (string) $reason : 'unknown error');
        $lower = strtolower($message);

        $timedOut = str_contains($lower, 'timed out')
            || str_contains($lower, 'timeout')
            || str_contains($lower, 'curl error 28');

        if ($timedOut) {
            return new CorvoApiError(0, 'timeout', 'Request timed out after ' . $this->timeout . 's');
        }

        return new CorvoApiError(0, 'network_error', 'Network request failed: ' . $message);
    }

    private function headerValue(ResponseInterface $response, string $name): ?string
    {
        $value = $response->getHeaderLine($name);

        return $value !== '' ? $value : null;
    }

    private function validateBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('Corvo: invalid baseUrl: ' . $baseUrl);
        }

        if (
            strtolower($parts['scheme']) === 'http'
            && !$this->allowInsecureHttp
            && !self::isPrivateHost($parts['host'])
        ) {
            throw new InvalidArgumentException(
                'Corvo: http:// is only allowed for loopback/private hosts (got ' . $parts['host']
                . '). Use https:// or pass allow_insecure_http: true.',
            );
        }
    }

    private static function isPrivateHost(string $hostname): bool
    {
        $host = strtolower($hostname);

        if (
            $host === 'localhost'
            || $host === '[::1]'
            || $host === '::1'
            || str_ends_with($host, '.localhost')
        ) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $octets = array_map('intval', explode('.', $host));

        return $octets[0] === 127
            || $octets[0] === 10
            || $octets[0] === 0
            || ($octets[0] === 192 && $octets[1] === 168)
            || ($octets[0] === 172 && $octets[1] >= 16 && $octets[1] <= 31)
            || ($octets[0] === 169 && $octets[1] === 254);
    }
}
