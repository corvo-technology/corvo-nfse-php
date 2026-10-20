# corvo-nfse

**The PHP SDK for the [Corvo](https://corvo.tech) NFS-e API.** Register companies, calculate taxes, issue Brazilian service invoices (NFS-e), follow them by webhook and download the PDF and XML, from PHP 8.2+.

- **Result-style errors.** API, network and timeout failures return `new Result(null, $error)`: a `CorvoApiError` carrying the server's stable `code`, the HTTP `status`, the `requestId`, field-level `details` and `retryAfter`. Throw `$result->error` yourself if you prefer exceptions.
- **Pagination built in.** Every list returns a `Result` wrapping `{data, pagination}`, and `iterate()` is a generator that walks all pages.
- **Safe retries.** Issuing an invoice and sending an email accept an idempotency key.
- **Webhook signature check** in constant time.
- Built on Guzzle 7, `phpstan` level 9 clean. Library only, no CLI.

Full API reference: <https://corvo.tech/en/docs>.

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
    'taxRegime' => 'Simples Nacional', // 'MEI' | 'Simples Nacional' | 'Lucro Presumido' | 'Lucro Real'
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

Use `'options' => ['type' => 'SCHEDULE']` with a future `competenceDate` to schedule the issuance. Request bodies and responses keep the server's camelCase keys.

### Cancel an invoice

Pass a reason (`CancellationReason::IssueError`, `ServiceNotProvided` or `Other`, or the raw string `issue_error`, `service_not_provided`, `other`) and a description. The description (up to 255 characters) is required, with at least 15 characters, for `authorized` and `processing` invoices, and optional for `queued` ones. `cancel()` returns the updated invoice, like `get()`.

```php
use CorvoNfse\Types\CancellationReason;
use CorvoNfse\Types\Invoice;

$result = $corvo->invoices->cancel(
    $invoiceId,
    CancellationReason::IssueError,
    'Valor emitido incorretamente',
);

if ($result->error !== null) {
    // 409 ERROR_INVOICE_NOT_CANCELLABLE: retry_pending, reconciliation_required or manual_review
    // 422: invalid description
    echo $result->error->code;
}

// Later, check the outcome:
$invoice = Invoice::fromArray($corvo->invoices->get($invoiceId)->data);

match ($invoice->status) {
    'cancelled' => print("Cancelled at {$invoice->cancelledAt}\n"),
    'cancelling' => print("Sefin is processing the cancellation\n"),
    'processing' => print("Still being issued; cancels once authorized\n"), // cancellationRequestedAt is set
    'authorized' => print("Sefin rejected it: {$invoice->lastErrorCode} {$invoice->lastErrorMessage}\n"),
    default => null,
};
```

- A `queued` invoice is cancelled at once (`200`, `status: "cancelled"`).
- An `authorized` invoice is cancelled at the Sefin (`202`, `status: "cancelling"`). It ends `cancelled`, or goes back to `authorized` with `lastErrorCode` and `lastErrorMessage` if the Sefin rejects it. Cancelling an issued NFS-e does not refund the credit.
- An invoice still being issued (`202`) stays `processing` with `cancellationRequestedAt` set and is cancelled once it is authorized.
- Calling `cancel()` again returns the current state: the first request wins.

The `Invoice` type exposes `cancellationReason`, `cancellationDescription`, `cancelledAt` and `cancellationRequestedAt` (all nullable). The webhooks `nfse.invoice.cancelled` and `nfse.invoice.cancellation_rejected` report the outcome.

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

`Webhooks::WEBHOOK_EVENTS` lists the 11 events you can subscribe to. The `webhook.test` event is only sent by `webhooks->test()`.

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
| `invoices` | `create`, `calculate`, `get`, `list`, `iterate`, `events`, `cancel`, `pdf`, `xml`, `downloadUrl`, `sendEmail` |
| `webhooks` | `list`, `iterate`, `create`, `remove`, `test` |
| `webhooks->deliveries` | `list`, `iterate`, `stats`, `retry` |
| `credits` | `balance`, `plans`, `history`, `iterateHistory` |
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
- **Idempotency.** `invoices->create` and `invoices->sendEmail` accept an idempotency key.

## Development

```bash
composer install
vendor/bin/phpunit    # unit tests; the contract test skips without env
vendor/bin/phpstan analyse
```

The contract test runs only when `CORVO_API_KEY` and `CORVO_BASE_URL` point to a reachable instance.

## License

MIT, see [LICENSE](LICENSE).
