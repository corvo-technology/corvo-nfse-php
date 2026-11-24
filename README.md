# corvo-nfse

**The PHP SDK for the [Corvo](https://corvo.tech) NFS-e API.** Register companies, calculate taxes, issue Brazilian service invoices (NFS-e), follow them by webhook and download the PDF and XML, from PHP 8.2+.

- **Result-style errors.** API, network and timeout failures return `new Result(null, $error)`: a `CorvoApiError` carrying the server's stable `code`, the HTTP `status`, the `requestId`, field-level `details` and `retryAfter`. Throw `$result->error` yourself if you prefer exceptions.
- **Pagination built in.** Every list returns a `Result` wrapping `{data, pagination}`, and `iterate()` is a generator that walks all pages.
- **Safe retries.** Issuing an invoice, sending an email and substituting an invoice accept an idempotency key.
- **Webhook signature check** in constant time.
- Built on Guzzle 7, `phpstan` clean. Library only, no CLI.

Full API reference: <https://corvo.tech/en/docs>. The machine-readable contract (OpenAPI 0.3.0, 92 operations) is at <https://corvo.tech/openapi.json>; a copy is kept in `spec/openapi.json` and a test checks that every operation has an SDK method. What changed in each version is in [CHANGELOG.md](CHANGELOG.md).

## Install

```bash
composer require corvo-technology/corvo-nfse
```

Requires PHP >= 8.2 and Guzzle 7 (installed automatically).

## Quickstart

Start with a `corvo_test_...` key: invoices issued with it have no fiscal value.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CorvoNfse\Client;

$corvo = new Client([
    'api_key' => 'corvo_test_...', // or set CORVO_API_KEY
    // 'base_url' => 'https://api.corvo.tech', // default; also read from CORVO_BASE_URL
]);

$result = $corvo->companies->list(limit: 10);

if ($result->error !== null) {
    // CorvoApiError: status, code, message, requestId, details, retryAfter
    error_log($result->error->code . ' ' . $result->error->requestId);
} else {
    foreach ($result->data['data'] as $company) {
        echo $company['legalName'], PHP_EOL;
    }
}
```

`new Client()` is the only call that throws: it raises `InvalidArgumentException` when no API key is configured or the base URL is invalid. The other exception is `iterate()`, which raises `CorvoApiError` when a page fails (a generator has no channel to return a `Result`):

```php
try {
    foreach ($corvo->invoices->iterate(['companyId' => $companyId]) as $invoice) {
        echo $invoice['nfseNumber'], PHP_EOL;
    }
} catch (\CorvoNfse\CorvoApiError $error) {
    echo $error->code, ' ', $error->requestId;
}
```

### Register a company

```php
$company = $corvo->companies->create([
    'legalName' => 'Empresa Exemplo Ltda',
    'taxId' => '11222333000181',
    'taxRegime' => 'Simples Nacional', // 'MEI' | 'Simples Nacional' (the only regimes Corvo serves)
    'simplesRate' => '6.54', // required for Simples Nacional: effective DAS rate (%), ignored for MEI
    'municipalityCode' => '3550308',   // IBGE code, 7 digits
    'defaultServiceCode' => '010101',  // optional: used when an invoice omits service.code
]);
```

In `live`, also upload the A1 certificate (below). The company is ready when `status` is `ready` and `setupGaps` is empty.

```php
$corvo->certificates->upload(
    companyId: $companyId,
    file: file_get_contents('/secrets/certificado.p12'),
    password: 'segredo',
    fileName: 'certificado.p12', // optional
);
```

### Estimate the taxes before issuing

Nothing is issued, stored or charged.

```php
$estimate = $corvo->invoices->calculate([
    'companyTaxId' => '11222333000181',
    'service' => ['description' => 'Consultoria'],
    'amount' => 1500,
    'competenceDate' => '2026-10-16',
]);
// $estimate->data['status'] is 'ready' or 'blocked': a fiscal gap is data, not an error.
```

### Issue an invoice

Issuing is asynchronous: a successful call means the request was accepted, not that the invoice is authorized. Follow the status with `invoices->get()` or webhooks.

```php
$result = $corvo->invoices->create(
    [
        'companyTaxId' => '11222333000181', // or 'companyId'
        // 'customer' is optional; when sent, 'taxId' (CPF or CNPJ) is required.
        'customer' => ['taxId' => '52998224725', 'name' => 'Cliente', 'email' => 'cliente@example.com'],
        'service' => ['description' => 'Consultoria'], // code and city default to the company's
        'amount' => 150.50,
        'competenceDate' => '2026-10-16',
        'options' => ['email' => ['send' => true]],
    ],
    idempotencyKey: 'pedido_42', // sent as Idempotency-Key: the same key never issues twice
);

if ($result->error === null) {
    echo $result->data['id'];
}
```

Request bodies and responses keep the server's camelCase keys (billing, schedules and imports answer in snake_case).

### Schedule or repeat an invoice

With `'options' => ['type' => 'SCHEDULE']` (or `'RECURRING'`) the answer is a **schedule**, not an invoice: the note only exists when the schedule runs (09:00 America/Sao_Paulo, Monday to Friday). `competenceDate` is the date of the first run and must be from tomorrow on.

```php
use CorvoNfse\Types\Schedule;

$result = $corvo->invoices->create([
    'companyTaxId' => '11222333000181',
    'service' => ['description' => 'Mensalidade'],
    'amount' => 300,
    'competenceDate' => '2026-12-01',
    'options' => [
        'type' => 'RECURRING',
        'recurrence' => ['frequency' => 'MONTHLY', 'endsOn' => '2027-12-01'], // WEEKLY | BIWEEKLY | MONTHLY
    ],
]);

if ($result->error === null && Schedule::isSchedule($result->data)) {
    $scheduleId = $result->data['id'];
    $corvo->schedules->listRuns($scheduleId);   // every attempt, newest first
    $corvo->schedules->pause($scheduleId);      // also resume, cancel, update, get, list
}
```

### Customers, CNPJ lookup and favorite services

```php
$cnpj = $corvo->cnpj->lookup('11222333000181');             // legalName, address, situation, simples, mei
$customers = $corvo->customers->search(['search' => 'ana', 'limit' => 20]);
$corvo->customers->update('52998224725', ['email' => 'ana@example.com']);
$corvo->serviceFavorites->create($companyId, [
    'label' => 'Consultoria', 'description' => 'Consultoria mensal', 'serviceCode' => '010101', 'amountCents' => 150000,
]);
```

### Replace an invoice and resolve manual review

```php
$corvo->invoices->substitute($invoiceId, ['description' => 'Valor corrigido'], idempotencyKey: 'sub_42');
$corvo->invoices->review('retry', [$invoiceId]);            // or 'discard'
```

### Accountant package

The monthly ZIP (`xml/`, `pdf/` and `resumo.csv`) can be downloaded or sent to the accountant.

```php
$corvo->accountantPackage->update($companyId, 'contador@example.com'); // null turns the monthly send off
$corvo->accountantPackage->send($companyId, '2026-10');
$summary = $corvo->invoicePackage->summary($companyId, '2026-10');
$zip = $corvo->invoicePackage->download($companyId, '2026-10');       // DownloadedFile
```

### Import a spreadsheet

```php
$template = $corvo->imports->downloadTemplate();                       // CSV template
$import = $corvo->imports->start(file_get_contents('notas.csv'), companyId: $companyId, fileName: 'notas.csv');
$job = $corvo->imports->get($import->data['id']);                      // snake_case: status, counters, ...
foreach ($corvo->imports->iterateRows($import->data['id'], ['status' => 'invalid']) as $row) {
    // fix with $corvo->imports->updateRow($id, $row['line'], [...])
}
$corvo->imports->emit($import->data['id'], sendEmail: true);           // $corvo->imports->discard($id) to give up
```

### Credits and plans

`credits->balance()` answers `balance` (plan notes left plus loose notes), `planRemaining`, `avulsoBalance` and `hardLimit`. Plan slugs are `free`, `solo`, `equipe`, `empresa` and `escala` (`self-hosted` on a self-hosted instance): treat them as open strings. Extra notes cost R$ 0,99; `credits->previewPurchase(10)` prices a purchase (buying is done in the dashboard).

To declare a deduction from the ISS base, send `'deduction'` (greater than 0 and less than `amount` minus `discount`). The invoice is still issued for the full `amount`, and you are responsible for the declared value.

### Issue up to 50 invoices at once

`createBatch` takes 1 to 50 items (they can belong to different companies). Items are checked one by one: valid ones are saved together, invalid ones come back `rejected` and are not saved. Send an `idempotencyKey` per item, so repeating the batch after a timeout returns the saved ones with `replayed`.

```php
$result = $corvo->invoices->createBatch([
    ['companyTaxId' => '11222333000181', 'idempotencyKey' => 'sheet-row-2',
     'service' => ['description' => 'Consultoria'], 'amount' => 150.50, 'competenceDate' => '2026-10-16'],
    ['companyTaxId' => '11222333000181', 'idempotencyKey' => 'sheet-row-3',
     'service' => ['description' => 'Manutenção'], 'amount' => 300, 'competenceDate' => '2026-10-16'],
]);

foreach ($result->data['results'] ?? [] as $item) {
    if ($item['status'] === 'rejected') {
        echo $item['index'], ' ', $item['error']['code'], ' ', $item['error']['message'], "\n";
    }
}
```

The route allows 10 batches per minute per tenant (`429` with `Retry-After`).

### Cancel an invoice

```php
$result = $corvo->invoices->cancel($invoiceId, 'issue_error', 'Wrong amount on the invoice');
// reason: 'issue_error' | 'service_not_provided' | 'other'
```

- `queued`: cancelled at once, `status` is `cancelled` with `cancelledAt` set.
- `authorized`: `status` is `cancelling` while the cancellation is processed at the Sefin. The issuance credit is not refunded.
- `processing`: stays `processing` with `cancellationRequestedAt` set and is cancelled once authorized.

The description (15 to 255 characters) is required for an authorized or processing invoice and optional for a queued one. Read the invoice again (or listen to the `nfse.invoice.cancelled` and `nfse.invoice.cancellation_rejected` webhooks) to see the outcome: `cancelled`, or back to `authorized` with `lastErrorCode` when the cancellation was rejected.

### Download the PDF or XML

The invoice must be `authorized` and have its documents ready.

```php
$pdf = $corvo->invoices->pdf($invoiceId);
if ($pdf->error === null) {
    file_put_contents('nfse.pdf', $pdf->data->content);
    // also $pdf->data->mimeType and $pdf->data->fileName
}
```

### Verify webhook signatures

Always verify against the raw request body: re-serialized JSON will never match.

```php
use CorvoNfse\Webhooks;

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CORVO_SIGNATURE'] ?? null;

if (!Webhooks::verifySignature($rawBody, $signature, $webhookSecret)) {
    http_response_code(400);
    exit;
}

$event = json_decode($rawBody, true); // deduplicate by $event['id']: the same event can arrive twice
```

`Webhooks::WEBHOOK_EVENTS` lists the 12 events you can subscribe to (including `nfse.invoice.taker_rejected`). A webhook belongs to the environment of the key that created it: a `corvo_test_` key cannot reach live webhooks, and URLs that resolve to private networks are refused. The `webhook.test` event is only sent by `webhooks->test()`.

## Errors

`CorvoNfse\CorvoApiError` fields:

| Field | Type | Meaning |
| --- | --- | --- |
| `status` | `int` | HTTP status (`0` for network and timeout failures) |
| `code` | `string` | The API's `ERROR_*` code, or `http_<status>`, `invalid_json`, `network_error`, `timeout` |
| `getMessage()` | `string` | Human-readable message |
| `details` | `array` | Field-level validation details (`field`, `code`, `message`) |
| `requestId` | `?string` | `requestId` body field or `X-Request-Id` header |
| `retryAfter` | `?int` | Seconds from the body or the `Retry-After` header (on `429`) |

`Result->data` is `null` for a deletion (`204`) or an empty body, and also on failure: check `->error`.

## Resources

Every method returns `CorvoNfse\Result`.

| Resource | Methods |
| --- | --- |
| `companies` | `create`, `get`, `update`, `remove`, `list`, `iterate` |
| `certificates` | `upload`, `list`, `remove` |
| `fiscalProfiles` | `list`, `upsert`, `approve` |
| `serviceTaxProfiles` | `list`, `create`, `update`, `remove`, `approve` |
| `dpsSequence` | `get`, `set` |
| `accountantPackage` | `get`, `update`, `send`, `listSends` |
| `invoicePackage` | `summary`, `download` |
| `serviceFavorites` | `list`, `create`, `remove` |
| `customers` | `search`, `iterate`, `get`, `update`, `remove` |
| `cnpj` | `lookup` |
| `invoices` | `create`, `createBatch`, `calculate`, `get`, `list`, `iterate`, `events`, `pdf`, `xml`, `downloadUrl`, `sendEmail`, `cancel`, `substitute`, `overview`, `review` |
| `schedules` | `list`, `iterate`, `get`, `update`, `pause`, `resume`, `cancel`, `listRuns` |
| `imports` | `start`, `list`, `get`, `emit`, `listRows`, `iterateRows`, `updateRow`, `discard`, `downloadTemplate`, `downloadErrors` |
| `integrations` | `listProviders` |
| `integrationConnections` | `list`, `create`, `get`, `validate`, `updateCredentials`, `activate`, `pause`, `disconnect`, `listRules`, `replaceRules`, `listEvents`, `listOperations` |
| `integrationOperations` | `completeCustomer`, `retry` |
| `fiscalPortal` | `status` |
| `webhooks` | `list`, `iterate`, `create`, `remove`, `test` |
| `webhooks->deliveries` | `list`, `iterate`, `stats`, `retry` |
| `credits` | `balance`, `plans`, `history`, `iterateHistory`, `previewPurchase` |
| `billing` | `plan`, `usage` |
| `plans` | `list`, `get`, `quote` (public price catalog) |
| `health` | `live`, `ready` |

Changing plans and the billing portal exist only in the dashboard, so they are not in the SDK.

Readonly wire types live in `CorvoNfse\Types` (`Company`, `Invoice`, `Webhook`, `QuotaStatus`, ...). Resources return the raw decoded payload; call `Type::fromArray($result->data)` for the typed view.

## Configuration

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `api_key` | `string` | `getenv('CORVO_API_KEY')` | Required. Sent as `X-API-Key` |
| `base_url` | `string` | `getenv('CORVO_BASE_URL')`, then `https://api.corvo.tech` | |
| `prefix` | `string` | `v1` | |
| `timeout` | `float` | `30.0` | Seconds |
| `allow_insecure_http` | `bool` | `false` | `http://` is only accepted for loopback and private hosts unless enabled |
| `http_client` | `GuzzleHttp\ClientInterface` | built-in Guzzle client | Bring your own handler stack or mock |

## Behavior

- **Auth.** Every request sends `X-API-Key`. Keys are `corvo_test_...` or `corvo_live_...` and belong to one account and environment; the key decides the environment.
- **Responses.** Single resources and list items come back as the API's `data`; lists add `pagination`.
- **Idempotency.** `invoices->create`, `invoices->sendEmail` and `invoices->substitute` accept an idempotency key (`Idempotency-Key` header). The batch takes an `idempotencyKey` per item.
- **User-Agent.** Every request sends `corvo-nfse-php/<version>`.

## Development

```bash
composer install
vendor/bin/phpunit    # unit tests; the contract test skips without env
vendor/bin/phpstan analyse
```

`test/Contract/OperationsTest.php` reads `spec/openapi.json` and fails when an operation of the contract has no SDK method, or when a method sends another HTTP method or path than the contract declares. To follow a new API version, replace `spec/openapi.json` (download <https://corvo.tech/openapi.json>), run the tests and add the missing methods.

The live contract test runs only when `CORVO_API_KEY` and `CORVO_BASE_URL` point to a reachable instance.

## Publishing

Packagist reads the git tags, so a release is a tag:

1. Bump `CorvoNfse\Version::VERSION` and add the entry in `CHANGELOG.md`.
2. `git tag v0.3.0 && git push origin v0.3.0`.
3. The `Release` workflow checks that the tag matches `Version::VERSION`, runs PHPStan and the tests, and creates the GitHub release with generated notes (it uses the built-in `GITHUB_TOKEN`; no secret is needed).

One-time setup for Packagist (the repository owner): submit the repository at <https://packagist.org/packages/submit> (package `corvo-technology/corvo-nfse`), then in the repository's Settings > Webhooks add the Packagist webhook (or install the Packagist GitHub app) so every push and tag updates the package by itself. `API_TOKEN` and the webhook secret live on Packagist and GitHub, not in the workflow.

## License

MIT, see [LICENSE](LICENSE).
