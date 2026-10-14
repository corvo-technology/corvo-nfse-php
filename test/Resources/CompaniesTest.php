<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Resources;

use CorvoNfse\Tests\Support\MockApi;
use PHPUnit\Framework\TestCase;

final class CompaniesTest extends TestCase
{
    private const COMPANY = [
        'id' => '09947127-2702-4f6d-9da8-cb463608d812',
        'tenantId' => '6247dfd9-0adc-4bf6-aac9-ee9dc1e7ca8b',
        'legalName' => 'Empresa Exemplo Tecnologia LTDA',
        'tradeName' => 'Empresa Exemplo TESTE',
        'taxId' => '41834687000148',
        'municipalReg' => '123456',
        'municipalityCode' => '3170206',
        'taxRegime' => 'Simples Nacional',
        'simpleNational' => true,
        'specialRegime' => null,
        'addressStreet' => 'Rua Exemplo de Testes',
        'addressNumber' => '100',
        'addressComplement' => null,
        'addressDistrict' => 'Centro',
        'addressCity' => 'Uberlândia',
        'addressState' => 'MG',
        'addressZip' => '38400000',
        'fiscalSchemaVersion' => 'v2_02',
        'environment' => 'test',
        'status' => 'ready',
        'createdAt' => '2026-10-12T13:04:38.985Z',
        'updatedAt' => '2026-10-12T13:04:40.230Z',
        'deletedAt' => null,
        'setupGaps' => [],
    ];

    public function testCreatePostsCompaniesWithWireBody(): void
    {
        [$client, $mock] = MockApi::client([MockApi::json(['data' => self::COMPANY], 201)]);

        $result = $client->companies->create([
            'legalName' => 'Empresa Exemplo Tecnologia LTDA',
            'taxId' => '41.834.687/0001-48',
            'taxRegime' => 'Simples Nacional',
            'address' => [
                'street' => 'Rua Exemplo',
                'number' => '100',
                'district' => 'Centro',
                'city' => 'Uberlândia',
                'state' => 'MG',
                'zip' => '38400-000',
            ],
        ]);

        self::assertNull($result->error);
        self::assertSame(self::COMPANY['id'], $result->data['id']);

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('http://localhost:4000/v1/companies', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('Simples Nacional', $body['taxRegime']);
        self::assertSame('Uberlândia', $body['address']['city']);
    }

    public function testListReturnsPaginationEnvelope(): void
    {
        [$client] = MockApi::client([
            MockApi::json([
                'data' => [self::COMPANY],
                'pagination' => ['limit' => 10, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $result = $client->companies->list(limit: 10);

        self::assertNull($result->error);
        self::assertCount(1, $result->data['data']);
        self::assertSame(10, $result->data['pagination']['limit']);
        self::assertFalse($result->data['pagination']['hasMore']);
    }

    public function testGetUpdateRemoveUseTheExpectedMethods(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json(['data' => self::COMPANY]),
            MockApi::json(['data' => self::COMPANY]),
            new \GuzzleHttp\Psr7\Response(204),
        ]);

        $id = self::COMPANY['id'];

        $get = $client->companies->get($id);
        $getRequest = $mock->getLastRequest();
        $update = $client->companies->update($id, ['tradeName' => 'Novo Nome']);
        $updateRequest = $mock->getLastRequest();
        $remove = $client->companies->remove($id);
        $removeRequest = $mock->getLastRequest();

        self::assertNotNull($getRequest);
        self::assertNotNull($updateRequest);
        self::assertNotNull($removeRequest);

        self::assertSame('GET', $getRequest->getMethod());
        self::assertSame('PATCH', $updateRequest->getMethod());
        self::assertSame(
            'http://localhost:4000/v1/companies/' . $id,
            (string) $updateRequest->getUri(),
        );
        self::assertSame('DELETE', $removeRequest->getMethod());

        self::assertSame('Novo Nome', json_decode((string) $updateRequest->getBody(), true)['tradeName']);

        self::assertNull($get->error);
        self::assertNull($update->error);
        self::assertTrue($remove->isOk());
        self::assertNull($remove->data);
        self::assertNull($remove->error);
    }

    public function testIterateWalksEveryCursorPage(): void
    {
        [$client, $mock] = MockApi::client([
            MockApi::json([
                'data' => [['id' => 'c_1'], ['id' => 'c_2']],
                'pagination' => ['limit' => 2, 'hasMore' => true, 'nextCursor' => 'cursor_2'],
            ]),
            MockApi::json([
                'data' => [['id' => 'c_3']],
                'pagination' => ['limit' => 2, 'hasMore' => false, 'nextCursor' => null],
            ]),
        ]);

        $generator = $client->companies->iterate(['limit' => 2]);
        $generator->rewind();
        $firstRequest = $mock->getLastRequest();
        self::assertNotNull($firstRequest);

        $items = [];
        foreach ($generator as $item) {
            $items[] = $item;
        }

        $secondRequest = $mock->getLastRequest();
        self::assertNotNull($secondRequest);

        self::assertCount(3, $items);
        self::assertSame('c_3', $items[2]['id']);

        self::assertStringNotContainsString('cursor=', (string) $firstRequest->getUri());
        self::assertSame(
            'http://localhost:4000/v1/companies?limit=2&cursor=cursor_2',
            (string) $secondRequest->getUri(),
        );
    }
}
