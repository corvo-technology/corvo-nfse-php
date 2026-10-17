<?php

declare(strict_types=1);

namespace CorvoNfse\Tests;

use CorvoNfse\CorvoApiError;
use CorvoNfse\DownloadedFile;
use CorvoNfse\Tests\Support\MockApi;
use CorvoNfse\Transport;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    public function testBuildsUrlWithV1PrefixMethodQueryAndAuthHeaders(): void
    {
        [$transport, $mock] = MockApi::transport([MockApi::json(['data' => ['ok' => true]])]);

        $result = $transport->request('GET', '/companies', query: ['limit' => 10, 'cursor' => null]);

        self::assertTrue($result->isOk());
        self::assertSame(['ok' => true], $result->data);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/companies?limit=10', (string) $request->getUri());
        self::assertSame(MockApi::API_KEY, $request->getHeaderLine('X-API-Key'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testSendsJsonBodyAndIdempotencyKey(): void
    {
        [$transport, $mock] = MockApi::transport([MockApi::json(['data' => ['id' => 'inv_1']])]);

        $transport->request('POST', '/invoices', json: ['amount' => 10], idempotencyKey: 'pedido_123');

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('pedido_123', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['amount' => 10], json_decode((string) $request->getBody(), true));
    }

    public function testUnwrapNoneReturnsTheWholeBody(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json(['status' => 'ok', 'uptime' => 10867]),
        ]);

        $result = $transport->request('GET', '/health/live', unwrap: Transport::UNWRAP_NONE);

        self::assertNull($result->error);
        self::assertSame(['status' => 'ok', 'uptime' => 10867], $result->data);
    }

    public function testReturnsNullOn204NoContent(): void
    {
        [$transport] = MockApi::transport([new \GuzzleHttp\Psr7\Response(204)]);

        $result = $transport->request('DELETE', '/companies/c_1');

        self::assertTrue($result->isOk());
        self::assertNull($result->data);
        self::assertNull($result->error);
    }

    public function testReturnsNullOnEmptyBody(): void
    {
        [$transport] = MockApi::transport([new \GuzzleHttp\Psr7\Response(200, [], '')]);

        $result = $transport->request('GET', '/companies/c_1');

        self::assertTrue($result->isOk());
        self::assertNull($result->data);
    }

    public function testMaps400ToTypedErrorWithDetails(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json([
                'code' => 'invalid_payload',
                'message' => 'Invalid payload',
                'details' => [
                    ['field' => 'customer.taxId', 'code' => 'invalid', 'message' => 'Invalid tax id'],
                ],
                'requestId' => 'req_7f3',
            ], 400),
        ]);

        $result = $transport->request('POST', '/invoices', json: []);

        self::assertNull($result->data);
        self::assertInstanceOf(CorvoApiError::class, $result->error);
        $error = $result->error;
        self::assertSame(400, $error->status);
        self::assertSame('invalid_payload', $error->code);
        self::assertSame('Invalid payload', $error->getMessage());
        self::assertSame('req_7f3', $error->requestId);
        self::assertCount(1, $error->details);
        self::assertSame('customer.taxId', $error->details[0]['field']);
    }

    public function testFallsBackOnIncompleteErrorBodyAndJoinsMessageArrays(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json(['message' => ['campo inválido', 'outro campo']], 422, ['X-Request-Id' => 'req_hdr']),
        ]);

        $result = $transport->request('POST', '/companies', json: []);

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame(422, $error->status);
        self::assertSame('http_422', $error->code);
        self::assertSame('campo inválido; outro campo', $error->getMessage());
        self::assertSame('req_hdr', $error->requestId);
        self::assertSame([], $error->details);
        self::assertNull($error->retryAfter);
    }

    public function testMapsRetryAfterHeaderOnRateLimit(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json(['message' => 'Too many requests'], 429, ['Retry-After' => '45']),
        ]);

        $result = $transport->request('GET', '/companies');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame(429, $error->status);
        self::assertSame('http_429', $error->code);
        self::assertSame(45, $error->retryAfter);
    }

    public function testMapsNonJsonErrorBodyToFallbacks(): void
    {
        [$transport] = MockApi::transport([
            new \GuzzleHttp\Psr7\Response(502, [], '<html>Bad Gateway</html>'),
        ]);

        $result = $transport->request('GET', '/companies');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame(502, $error->status);
        self::assertSame('http_502', $error->code);
        self::assertSame('Request failed with status 502', $error->getMessage());
    }

    public function testMapsInvalidJsonOnSuccessToInvalidJsonError(): void
    {
        [$transport] = MockApi::transport([
            new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], 'not-json'),
        ]);

        $result = $transport->request('GET', '/companies');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame('invalid_json', $error->code);
        self::assertSame(200, $error->status);
    }

    public function testMapsNetworkRejectionToNetworkError(): void
    {
        [$transport] = MockApi::transport([
            new ConnectException('Connection refused', new Request('GET', 'http://localhost:4000/v1/companies')),
        ]);

        $result = $transport->request('GET', '/companies');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame('network_error', $error->code);
        self::assertSame(0, $error->status);
        self::assertStringContainsString('Connection refused', $error->getMessage());
    }

    public function testMapsTimeoutToTimeoutError(): void
    {
        [$transport] = MockApi::transport([
            new ConnectException(
                'Operation timed out after 30001 milliseconds with 0 bytes received',
                new Request('GET', 'http://localhost:4000/v1/companies'),
            ),
        ]);

        $result = $transport->request('GET', '/companies');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame('timeout', $error->code);
        self::assertSame(0, $error->status);
    }

    public function testRequestRawReturnsBytesMimeTypeAndFileName(): void
    {
        [$transport] = MockApi::transport([
            new \GuzzleHttp\Psr7\Response(
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="nfse-12345.pdf"',
                ],
                '%PDF-1.7 conteudo',
            ),
        ]);

        $result = $transport->requestRaw('GET', '/invoices/inv_1/pdf');

        self::assertNull($result->error);
        self::assertInstanceOf(DownloadedFile::class, $result->data);
        self::assertSame('%PDF-1.7 conteudo', $result->data->content);
        self::assertSame('application/pdf', $result->data->mimeType);
        self::assertSame('nfse-12345.pdf', $result->data->fileName);
    }

    public function testRequestRawMapsErrorResponse(): void
    {
        [$transport] = MockApi::transport([MockApi::json(['code' => 'not_found', 'message' => 'Nope'], 404)]);

        $result = $transport->requestRaw('GET', '/invoices/inv_1/pdf');

        $error = $result->error;
        self::assertInstanceOf(CorvoApiError::class, $error);
        self::assertSame(404, $error->status);
        self::assertSame('not_found', $error->code);
    }

    public function testListWrapsItemsAndPagination(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json([
                'data' => [['id' => 'c_1'], ['id' => 'c_2']],
                'pagination' => ['limit' => 10, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $transport->list('/companies', null, static fn (mixed $item): mixed => $item);

        self::assertNull($result->error);
        self::assertSame([['id' => 'c_1'], ['id' => 'c_2']], $result->data['data']);
        self::assertFalse($result->data['pagination']['hasMore']);
    }

    public function testIterateWalksPagesUntilHasMoreIsFalse(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json([
                'data' => [['id' => 'c_1'], ['id' => 'c_2']],
                'pagination' => ['limit' => 2, 'hasMore' => true, 'nextCursor' => 'cur_2'],
            ]),
            MockApi::json([
                'data' => [['id' => 'c_3']],
                'pagination' => ['limit' => 2, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $items = iterator_to_array($transport->iterate('/companies', [], static fn (mixed $item): mixed => $item), false);

        self::assertSame([['id' => 'c_1'], ['id' => 'c_2'], ['id' => 'c_3']], $items);
    }

    public function testIterateThrowsTypedErrorOnFirstPageFailure(): void
    {
        [$transport] = MockApi::transport([
            MockApi::json(['code' => 'unauthorized', 'message' => 'Invalid API key'], 401),
        ]);

        $this->expectException(CorvoApiError::class);
        $this->expectExceptionMessage('Invalid API key');

        iterator_to_array($transport->iterate('/companies', [], static fn (mixed $item): mixed => $item), false);
    }

    public function testRejectsInsecurePublicHttpBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('allow_insecure_http');

        new Transport(['api_key' => MockApi::API_KEY, 'base_url' => 'http://api.exemplo.com']);
    }

    public function testAcceptsLoopbackAndPrivateHosts(): void
    {
        foreach (['http://localhost:4000', 'http://127.0.0.1:3000', 'http://192.168.0.10', 'http://10.1.2.3', 'http://172.16.0.9', 'http://169.254.1.1'] as $baseUrl) {
            $transport = new Transport(['api_key' => MockApi::API_KEY, 'base_url' => $baseUrl]);
            self::assertInstanceOf(Transport::class, $transport);
        }
    }

    public function testAcceptsPublicHttpWithAllowInsecureHttp(): void
    {
        $transport = new Transport([
            'api_key' => MockApi::API_KEY,
            'base_url' => 'http://api.exemplo.com',
            'allow_insecure_http' => true,
        ]);

        self::assertInstanceOf(Transport::class, $transport);
    }

    public function testRejectsInvalidBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid baseUrl');

        new Transport(['api_key' => MockApi::API_KEY, 'base_url' => 'not-a-url']);
    }

    public function testRequiresApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('api_key is required');

        new Transport(['base_url' => MockApi::BASE_URL]);
    }
}
