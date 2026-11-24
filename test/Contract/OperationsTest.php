<?php

declare(strict_types=1);

namespace CorvoNfse\Tests\Contract;

use CorvoNfse\Client;
use CorvoNfse\DownloadedFile;
use CorvoNfse\Result;
use CorvoNfse\Tests\Support\MockApi;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract coverage: `spec/openapi.json` (contract 0.3.0) is the source of truth.
 *
 * Every operationId of the contract must have an entry in {@see operations()} (and no entry may
 * be missing from the contract), and every entry is called against a mocked HTTP layer to check
 * that the SDK sends the HTTP method and path the contract declares, the query string, the body,
 * the `x-api-key` header and (when the contract lists it) `Idempotency-Key`.
 *
 * When the API changes: replace `spec/openapi.json`, run the tests, and add the new operations.
 */
final class OperationsTest extends TestCase
{
    private const SPEC = __DIR__ . '/../../spec/openapi.json';

    /** Operations that answer with the file itself instead of `{data}`. */
    private const RAW = [
        'invoices_downloadPdf',
        'invoices_downloadXml',
        'invoicePackage_download',
        'imports_downloadTemplate',
        'imports_downloadErrors',
    ];

    /** Operations that answer with `{data, pagination}`. */
    private const PAGED = [
        'companies_list',
        'certificates_list',
        'credits_listHistory',
        'credits_listPlans',
        'customers_search',
        'imports_listRows',
        'plans_list',
        'invoices_list',
        'invoices_listEvents',
        'schedules_list',
        'schedules_listRuns',
        'webhooks_list',
        'webhookDeliveries_list',
    ];

    /** @return array<string, mixed> */
    private static function spec(): array
    {
        $decoded = json_decode((string) file_get_contents(self::SPEC), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * operationId => [call, path parameter values in order, expected query, expected JSON body
     * (null: no body), sends Idempotency-Key].
     *
     * @return array<string, array{0: callable(Client): mixed, 1: list<string>, 2: array<string, string>, 3: array<mixed>|null, 4?: bool}>
     */
    private static function operations(): array
    {
        $invoice = ['companyTaxId' => '11222333000181', 'amount' => 10, 'competenceDate' => '2026-11-25', 'service' => ['description' => 'x']];

        return [
            'accountantPackage_get' => [fn (Client $c) => $c->accountantPackage->get('C1'), ['C1'], [], null],
            'accountantPackage_update' => [fn (Client $c) => $c->accountantPackage->update('C1', 'contador@example.com'), ['C1'], [], ['accountantEmail' => 'contador@example.com']],
            'accountantPackage_send' => [fn (Client $c) => $c->accountantPackage->send('C1', '2026-10'), ['C1'], [], ['month' => '2026-10']],
            'accountantPackage_listSends' => [fn (Client $c) => $c->accountantPackage->listSends('C1'), ['C1'], [], null],
            'billing_getPlan' => [fn (Client $c) => $c->billing->plan(), [], [], null],
            'billing_getUsage' => [fn (Client $c) => $c->billing->usage(), [], [], null],
            'certificates_list' => [fn (Client $c) => $c->certificates->list('C1', 5, 'cur'), ['C1'], ['limit' => '5', 'cursor' => 'cur'], null],
            'certificates_revoke' => [fn (Client $c) => $c->certificates->remove('C1', 'K1'), ['C1', 'K1'], [], null],
            'certificates_upload' => [fn (Client $c) => $c->certificates->upload('C1', 'bytes', 'pw'), ['C1'], [], null],
            'cnpj_lookup' => [fn (Client $c) => $c->cnpj->lookup('11222333000181'), ['11222333000181'], [], null],
            'companies_create' => [fn (Client $c) => $c->companies->create(['legalName' => 'X']), [], [], ['legalName' => 'X']],
            'companies_delete' => [fn (Client $c) => $c->companies->remove('C1'), ['C1'], [], null],
            'companies_get' => [fn (Client $c) => $c->companies->get('C1'), ['C1'], [], null],
            'companies_list' => [fn (Client $c) => $c->companies->list(5, 'cur'), [], ['limit' => '5', 'cursor' => 'cur'], null],
            'companies_update' => [fn (Client $c) => $c->companies->update('C1', ['tradeName' => 'Y']), ['C1'], [], ['tradeName' => 'Y']],
            'credits_getBalance' => [fn (Client $c) => $c->credits->balance(), [], [], null],
            'credits_listHistory' => [fn (Client $c) => $c->credits->history(5, 'cur'), [], ['limit' => '5', 'cursor' => 'cur'], null],
            'credits_listPlans' => [fn (Client $c) => $c->credits->plans(5, 'cur'), [], ['limit' => '5', 'cursor' => 'cur'], null],
            'credits_previewPurchase' => [fn (Client $c) => $c->credits->previewPurchase(10), [], ['units' => '10'], null],
            'customers_delete' => [fn (Client $c) => $c->customers->remove('52998224725'), ['52998224725'], [], null],
            'customers_get' => [fn (Client $c) => $c->customers->get('52998224725'), ['52998224725'], [], null],
            'customers_search' => [fn (Client $c) => $c->customers->search(['search' => 'ana', 'limit' => 5, 'cursor' => '20']), [], ['search' => 'ana', 'limit' => '5', 'cursor' => '20'], null],
            'customers_update' => [fn (Client $c) => $c->customers->update('52998224725', ['name' => 'Ana']), ['52998224725'], [], ['name' => 'Ana']],
            'dpsSequence_get' => [fn (Client $c) => $c->dpsSequence->get('C1'), ['C1'], [], null],
            'dpsSequence_set' => [fn (Client $c) => $c->dpsSequence->set('C1', 120), ['C1'], [], ['lastNumber' => 120]],
            'fiscalPortal_status' => [fn (Client $c) => $c->fiscalPortal->status(), [], [], null],
            'fiscalProfiles_approve' => [fn (Client $c) => $c->fiscalProfiles->approve('C1', 'P1'), ['C1', 'P1'], [], null],
            'fiscalProfiles_list' => [fn (Client $c) => $c->fiscalProfiles->list('C1'), ['C1'], [], null],
            'fiscalProfiles_upsert' => [fn (Client $c) => $c->fiscalProfiles->upsert('C1', ['taxRegime' => 'MEI']), ['C1'], [], ['taxRegime' => 'MEI']],
            'health_live' => [fn (Client $c) => $c->health->live(), [], [], null],
            'imports_discard' => [fn (Client $c) => $c->imports->discard('I1'), ['I1'], [], null],
            'imports_downloadErrors' => [fn (Client $c) => $c->imports->downloadErrors('I1'), ['I1'], [], null],
            'imports_downloadTemplate' => [fn (Client $c) => $c->imports->downloadTemplate(), [], [], null],
            'imports_emit' => [fn (Client $c) => $c->imports->emit('I1', true), ['I1'], [], ['sendEmail' => true]],
            'imports_get' => [fn (Client $c) => $c->imports->get('I1'), ['I1'], [], null],
            'imports_list' => [fn (Client $c) => $c->imports->list(10), [], ['limit' => '10'], null],
            'imports_listRows' => [fn (Client $c) => $c->imports->listRows('I1', ['status' => 'invalid', 'limit' => 20, 'cursor' => 'cur']), ['I1'], ['status' => 'invalid', 'limit' => '20', 'cursor' => 'cur'], null],
            'imports_start' => [fn (Client $c) => $c->imports->start("a,b\n1,2", 'C1', 'nfse.csv'), [], [], null],
            'imports_updateRow' => [fn (Client $c) => $c->imports->updateRow('I1', 7, ['amount' => '10']), ['I1', '7'], [], ['item' => ['amount' => '10']]],
            'integrationConnections_activate' => [fn (Client $c) => $c->integrationConnections->activate('N1'), ['N1'], [], null],
            'integrationConnections_create' => [fn (Client $c) => $c->integrationConnections->create(['provider' => 'p', 'companyId' => 'C1', 'credentials' => ['k' => 'v']]), [], [], ['provider' => 'p', 'companyId' => 'C1', 'credentials' => ['k' => 'v']]],
            'integrationConnections_disconnect' => [fn (Client $c) => $c->integrationConnections->disconnect('N1'), ['N1'], [], null],
            'integrationConnections_get' => [fn (Client $c) => $c->integrationConnections->get('N1'), ['N1'], [], null],
            'integrationConnections_list' => [fn (Client $c) => $c->integrationConnections->list(), [], [], null],
            'integrationConnections_listEvents' => [fn (Client $c) => $c->integrationConnections->listEvents('N1', 5), ['N1'], ['limit' => '5'], null],
            'integrationConnections_listOperations' => [fn (Client $c) => $c->integrationConnections->listOperations('N1', 5), ['N1'], ['limit' => '5'], null],
            'integrationConnections_listRules' => [fn (Client $c) => $c->integrationConnections->listRules('N1'), ['N1'], [], null],
            'integrationConnections_pause' => [fn (Client $c) => $c->integrationConnections->pause('N1'), ['N1'], [], null],
            'integrationConnections_replaceRules' => [fn (Client $c) => $c->integrationConnections->replaceRules('N1', [['sendEmail' => true, 'service' => ['description' => 'x']]]), ['N1'], [], ['rules' => [['sendEmail' => true, 'service' => ['description' => 'x']]]]],
            'integrationConnections_updateCredentials' => [fn (Client $c) => $c->integrationConnections->updateCredentials('N1', ['k' => 'v2']), ['N1'], [], ['credentials' => ['k' => 'v2']]],
            'integrationConnections_validate' => [fn (Client $c) => $c->integrationConnections->validate('N1'), ['N1'], [], null],
            'integrationOperations_completeCustomer' => [fn (Client $c) => $c->integrationOperations->completeCustomer('O1', ['taxId' => '52998224725']), ['O1'], [], ['taxId' => '52998224725']],
            'integrationOperations_retry' => [fn (Client $c) => $c->integrationOperations->retry('O1'), ['O1'], [], null],
            'integrations_listProviders' => [fn (Client $c) => $c->integrations->listProviders(), [], [], null],
            'invoicePackage_download' => [fn (Client $c) => $c->invoicePackage->download('C1', '2026-10', 'xml,pdf'), ['C1'], ['month' => '2026-10', 'include' => 'xml,pdf'], null],
            'invoicePackage_summary' => [fn (Client $c) => $c->invoicePackage->summary('C1', '2026-10'), ['C1'], ['month' => '2026-10'], null],
            'invoices_calculate' => [fn (Client $c) => $c->invoices->calculate($invoice), [], [], $invoice],
            'invoices_cancel' => [fn (Client $c) => $c->invoices->cancel('V1', 'other', 'descricao com mais de quinze'), ['V1'], [], ['reason' => 'other', 'description' => 'descricao com mais de quinze']],
            'invoices_create' => [fn (Client $c) => $c->invoices->create($invoice, 'idem-1'), [], [], $invoice, true],
            'invoices_createBatch' => [fn (Client $c) => $c->invoices->createBatch([$invoice]), [], [], ['invoices' => [$invoice]]],
            'invoices_createDownloadUrl' => [fn (Client $c) => $c->invoices->downloadUrl('V1', 'pdf'), ['V1'], [], ['format' => 'pdf']],
            'invoices_downloadPdf' => [fn (Client $c) => $c->invoices->pdf('V1'), ['V1'], [], null],
            'invoices_downloadXml' => [fn (Client $c) => $c->invoices->xml('V1'), ['V1'], [], null],
            'invoices_get' => [fn (Client $c) => $c->invoices->get('V1'), ['V1'], [], null],
            'invoices_list' => [fn (Client $c) => $c->invoices->list(['customerTaxId' => '52998224725', 'status' => 'authorized', 'limit' => 5]), [], ['customerTaxId' => '52998224725', 'status' => 'authorized', 'limit' => '5'], null],
            'invoices_listEvents' => [fn (Client $c) => $c->invoices->events('V1', ['limit' => 5]), ['V1'], ['limit' => '5'], null],
            'invoices_overview' => [fn (Client $c) => $c->invoices->overview('30d'), [], ['period' => '30d'], null],
            'invoices_review' => [fn (Client $c) => $c->invoices->review('retry', ['V1', 'V2']), [], [], ['action' => 'retry', 'invoiceIds' => ['V1', 'V2']]],
            'invoices_sendEmail' => [fn (Client $c) => $c->invoices->sendEmail('V1', 'idem-2', 'outro@example.com'), ['V1'], [], ['email' => 'outro@example.com'], true],
            'invoices_substitute' => [fn (Client $c) => $c->invoices->substitute('V1', ['description' => 'corrigir descricao do servico'], 'idem-3'), ['V1'], [], ['description' => 'corrigir descricao do servico'], true],
            'plans_get' => [fn (Client $c) => $c->plans->get('equipe', 'annual'), ['equipe'], ['cycle' => 'annual'], null],
            'plans_list' => [fn (Client $c) => $c->plans->list(5, 'cur', 'annual'), [], ['limit' => '5', 'cursor' => 'cur', 'cycle' => 'annual'], null],
            'schedules_cancel' => [fn (Client $c) => $c->schedules->cancel('S1'), ['S1'], [], null],
            'schedules_get' => [fn (Client $c) => $c->schedules->get('S1'), ['S1'], [], null],
            'schedules_list' => [fn (Client $c) => $c->schedules->list(['status' => 'active', 'companyId' => 'C1', 'limit' => 5, 'cursor' => '10']), [], ['status' => 'active', 'companyId' => 'C1', 'limit' => '5', 'cursor' => '10'], null],
            'schedules_listRuns' => [fn (Client $c) => $c->schedules->listRuns('S1', ['limit' => 5]), ['S1'], ['limit' => '5'], null],
            'schedules_pause' => [fn (Client $c) => $c->schedules->pause('S1'), ['S1'], [], null],
            'schedules_resume' => [fn (Client $c) => $c->schedules->resume('S1'), ['S1'], [], null],
            'schedules_update' => [fn (Client $c) => $c->schedules->update('S1', ['frequency' => 'MONTHLY']), ['S1'], [], ['frequency' => 'MONTHLY']],
            'serviceFavorites_create' => [fn (Client $c) => $c->serviceFavorites->create('C1', ['label' => 'a', 'description' => 'b', 'serviceCode' => '010101']), ['C1'], [], ['label' => 'a', 'description' => 'b', 'serviceCode' => '010101']],
            'serviceFavorites_delete' => [fn (Client $c) => $c->serviceFavorites->remove('C1', 'F1'), ['C1', 'F1'], [], null],
            'serviceFavorites_list' => [fn (Client $c) => $c->serviceFavorites->list('C1'), ['C1'], [], null],
            'serviceTaxProfiles_approve' => [fn (Client $c) => $c->serviceTaxProfiles->approve('C1', 'T1'), ['C1', 'T1'], [], null],
            'serviceTaxProfiles_create' => [fn (Client $c) => $c->serviceTaxProfiles->create('C1', ['name' => 'a', 'serviceCode' => '010101', 'effectiveFrom' => '2026-01-01']), ['C1'], [], ['name' => 'a', 'serviceCode' => '010101', 'effectiveFrom' => '2026-01-01']],
            'serviceTaxProfiles_delete' => [fn (Client $c) => $c->serviceTaxProfiles->remove('C1', 'T1'), ['C1', 'T1'], [], null],
            'serviceTaxProfiles_list' => [fn (Client $c) => $c->serviceTaxProfiles->list('C1', '2026-11-24'), ['C1'], ['date' => '2026-11-24'], null],
            'serviceTaxProfiles_update' => [fn (Client $c) => $c->serviceTaxProfiles->update('C1', 'T1', ['name' => 'b']), ['C1', 'T1'], [], ['name' => 'b']],
            'webhookDeliveries_list' => [fn (Client $c) => $c->webhooks->deliveries->list(24, 'test', 5, 'cur'), [], ['hours' => '24', 'environment' => 'test', 'limit' => '5', 'cursor' => 'cur'], null],
            'webhookDeliveries_retry' => [fn (Client $c) => $c->webhooks->deliveries->retry('D1'), ['D1'], [], null],
            'webhookDeliveries_stats' => [fn (Client $c) => $c->webhooks->deliveries->stats(24, 'live'), [], ['hours' => '24', 'environment' => 'live'], null],
            'webhooks_create' => [fn (Client $c) => $c->webhooks->create(['url' => 'https://example.com/h', 'events' => ['nfse.invoice.taker_rejected']]), [], [], ['url' => 'https://example.com/h', 'events' => ['nfse.invoice.taker_rejected']]],
            'webhooks_delete' => [fn (Client $c) => $c->webhooks->remove('W1'), ['W1'], [], null],
            'webhooks_list' => [fn (Client $c) => $c->webhooks->list(['limit' => 5, 'cursor' => 'cur']), [], ['limit' => '5', 'cursor' => 'cur'], null],
            'webhooks_test' => [fn (Client $c) => $c->webhooks->test('W1'), ['W1'], [], null],
        ];
    }

    /** @return array<string, array{string, string, string}> operationId => [METHOD, path template, content type] */
    public static function contractOperations(): array
    {
        $out = [];
        foreach (self::spec()['paths'] as $path => $item) {
            foreach ($item as $method => $operation) {
                if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }
                $out[$operation['operationId']] = [strtoupper($method), (string) $path, (string) $operation['operationId']];
            }
        }

        return $out;
    }

    public function testContractIsVersion030With94Operations(): void
    {
        $spec = self::spec();

        self::assertSame('0.3.0', $spec['info']['version']);
        self::assertCount(94, self::contractOperations());
    }

    public function testEveryContractOperationIsImplementedAndNothingElseIsMapped(): void
    {
        $contract = array_keys(self::contractOperations());
        $mapped = array_keys(self::operations());
        sort($contract);
        sort($mapped);

        self::assertSame([], array_values(array_diff($contract, $mapped)), 'operations of the contract with no SDK method');
        self::assertSame([], array_values(array_diff($mapped, $contract)), 'mapped operations that are not in the contract');
    }

    /** @return iterable<string, array{string}> */
    public static function operationIds(): iterable
    {
        foreach (array_keys(self::operations()) as $operationId) {
            yield $operationId => [$operationId];
        }
    }

    #[DataProvider('operationIds')]
    public function testOperationSendsTheRequestTheContractDeclares(string $operationId): void
    {
        [$call, $pathValues, $query, $body, $idempotent] = self::operations()[$operationId] + [4 => false];
        [$method, $template] = self::contractOperations()[$operationId];

        $spec = self::spec()['paths'][$template][strtolower($method)];
        $declaresIdempotency = false;
        foreach ($spec['parameters'] ?? [] as $parameter) {
            if (($parameter['$ref'] ?? '') === '#/components/parameters/IdempotencyKey' || ($parameter['name'] ?? '') === 'Idempotency-Key') {
                $declaresIdempotency = true;
            }
        }
        self::assertSame($declaresIdempotency, $idempotent, 'Idempotency-Key must be sent exactly when the contract declares it');

        $expectedPath = $template;
        foreach ($pathValues as $value) {
            $expectedPath = (string) preg_replace('/\{[^}]+\}/', rawurlencode($value), $expectedPath, 1);
        }
        self::assertStringNotContainsString('{', $expectedPath, 'path parameters were not all supplied');

        if (in_array($operationId, self::RAW, true)) {
            $response = new Response(200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="f.bin"'], 'BYTES');
        } elseif (in_array($operationId, self::PAGED, true)) {
            $response = MockApi::json(['data' => [['id' => 'x']], 'pagination' => ['limit' => 5, 'hasMore' => false, 'nextCursor' => null]]);
        } else {
            $response = MockApi::json(['data' => ['id' => 'x']]);
        }

        [$client, $mock] = MockApi::client([$response]);
        $result = $call($client);

        self::assertInstanceOf(Result::class, $result);
        self::assertNull($result->error, $operationId . ' returned an error');

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertSame($method, $request->getMethod());
        self::assertSame('/v1' . $expectedPath, $request->getUri()->getPath());
        self::assertSame(MockApi::API_KEY, $request->getHeaderLine('x-api-key'));
        self::assertStringStartsWith('corvo-nfse-php/0.3.0', $request->getHeaderLine('User-Agent'));

        parse_str($request->getUri()->getQuery(), $actualQuery);
        self::assertEquals($query, $actualQuery);

        if ($idempotent) {
            self::assertStringStartsWith('idem-', $request->getHeaderLine('Idempotency-Key'));
        } else {
            self::assertFalse($request->hasHeader('Idempotency-Key'));
        }

        if ($body !== null) {
            self::assertSame($body, json_decode((string) $request->getBody(), true));
        }

        if (in_array($operationId, self::RAW, true)) {
            self::assertInstanceOf(DownloadedFile::class, $result->data);
            self::assertSame('BYTES', $result->data->content);
        }
    }

    public function testMultipartOperationsSendTheDeclaredFields(): void
    {
        [$client, $mock] = MockApi::client([MockApi::json(['data' => ['id' => 'i1', 'status' => 'processing', 'duplicate_of' => null]], 202)]);
        $client->imports->start("a,b\n1,2", 'C1', 'nfse.csv');

        $request = $mock->getLastRequest();
        self::assertNotNull($request);
        self::assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        self::assertStringContainsString('name="companyId"', $body);
        self::assertStringContainsString('name="file"; filename="nfse.csv"', $body);
        self::assertStringContainsString("a,b\n1,2", $body);
    }
}
