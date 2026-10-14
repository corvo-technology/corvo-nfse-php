# corvo-nfse

**The PHP SDK for the Corvo NFS-e API — result-style errors, cursor pagination you don't have to think about, and webhook signatures that verify in one call.**

Issue, list, download, and reconcile Brazilian electronic service invoices (NFS-e) from any PHP 8.2+ app. Built on Guzzle 7, fully typed (PSR-4, `phpstan` level 9 clean), library only — no CLI.

## Why corvo-nfse

- **Errors that never throw.** API, network, and timeout failures return `new Result(null, $error)` — a `CorvoApiError` carrying the server's stable `code`, HTTP `status`, `requestId`, field-level `details`, and `retryAfter`. Throw `$result->error` yourself if you prefer exceptions.
- **Self-hosted first.** Point it at your own Corvo install; the public default `https://api.corvo.tech` only applies when you don't.
- **Pagination you don't have to think about.** Every list returns `Result` wrapping `{data, pagination}` with an opaque cursor. Want every page? `foreach ($corvo->invoices->iterate(...) as $invoice)` walks them for you.
- **Fully typed.** Readonly wire types mirror the verified API contract; request bodies keep the server's camelCase keys untouched.

## Install

```bash
composer require corvo-technology/corvo-nfse
```

Requires PHP >= 8.2 and Guzzle 7 (installed automatically).

## Quickstart

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use CorvoNfse\Client;

$corvo = new Client([
    'api_key' => 'corvo_live_xxx',          // or set CORVO_API_KEY
    'base_url' => 'https://api.corvo.tech', // or CORVO_BASE_URL for your self-hosted install
]);

$result = $corvo->companies->list(limit: 10);

if ($result->error !== null) {
    // CorvoApiError: status, code, message, requestId, details, retryAfter
    error_log($result->error->code . ' ' . $result->error->requestId);
} else {
    foreach ($result->data['data'] as $company) {
        echo $company['legalName'], PHP_EOL;
    }
    echo $result->data['pagination']['nextCursor'] ?? 'last page', PHP_EOL;
}
```

Errors are values, not exceptions:

```php
use CorvoNfse\Result;

$result = $corvo->companies->get('invalid');

if ($result->error !== null) {
    echo $result->error->status;   // 404
    echo $result->error->code;     // not_found (stable server code)
    throw $result->error;          // optional: CorvoApiError extends \Exception
}
```

The single documented exception: `iterate()` raises `CorvoNfse\CorvoApiError` on a **page** failure (a generator has no channel to return a `Result`). Wrap it in `try/catch` if the input is untrusted:

```php
try {
    foreach ($corvo->invoices->iterate(['companyId' => $companyId]) as $invoice) {
        // ...
    }
} catch (\CorvoNfse\CorvoApiError $error) {
    echo $error->code, ' ', $error->requestId;
}
```

## Issue and download an NFS-e

```php
$result = $corvo->invoices->create(
    [
        'companyId' => $companyId,
        'customer' => [
            'name' => 'Cliente Exemplo LTDA',
            'taxId' => '52998224725',
            'email' => 'cliente@example.com',
        ],
        'service' => [
            'code' => '01.01.01',
            'municipalityCode' => '3170206',
            'description' => 'Serviços de consultoria',
        ],
        'amount' => 1500.00,
        'competenceDate' => '2026-10-12',
    ],
    idempotencyKey: 'pedido_42', // sent as Idempotency-Key — safe to retry
);

if ($result->error === null) {
    echo $result->data['id']; // invoice id (unwrapped from data.props)
}

// Raw bytes come back as a CorvoNfse\DownloadedFile
$pdf = $corvo->invoices->pdf($result->data['id']);
if ($pdf->error === null) {
    file_put_contents('nfse.pdf', $pdf->data->content);
    // $pdf->data->mimeType === 'application/pdf'
    // $pdf->data->fileName === 'nfse-12345.pdf'
}
```

## Certificate upload

```php
$upload = $corvo->certificates->upload(
    companyId: $companyId,
    file: file_get_contents('/secrets/certificado.p12'),
    password: 'segredo',
    fileName: 'certificado.p12', // optional, defaults to certificate.p12
);
```

## Verify webhook signatures

Always verify against the **raw** request body — re-serialized JSON will never match:

```php
use CorvoNfse\Webhooks;

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CORVO_SIGNATURE'] ?? null;

if (!Webhooks::verifySignature($rawBody, $signature, $webhookSecret)) {
    http_response_code(400);
    exit;
}

$event = json_decode($rawBody, true);
```

`Webhooks::WEBHOOK_EVENTS` lists all 9 subscribable events (`nfse.invoice.authorized`, `webhook.test`, ...).

## Pagination and iteration

```php
$page = $corvo->companies->list(limit: 50);
$page->data['pagination']; // ['limit' => 50, 'hasMore' => true, 'nextCursor' => 'eyJ...']

// Or forget about cursors entirely:
foreach ($corvo->credits->iterateHistory(['limit' => 100]) as $transaction) {
    // ...
}
```

`iterate()` is a PHP generator that follows `hasMore`/`nextCursor` for you.

## Errors

`CorvoNfse\CorvoApiError` mirrors the shared SDK contract:

| Field | Type | Meaning |
| --- | --- | --- |
| `status` | `int` | HTTP status (`0` for network/timeout failures) |
| `code` | `string` | Stable server code, or `http_<status>`, `invalid_json`, `network_error`, `timeout` |
| `getMessage()` | `string` | Human-readable message |
| `details` | `array` | Field-level validation details (`field`, `code`, `message`) |
| `requestId` | `?string` | `requestId` body field or `X-Request-Id` header |
| `retryAfter` | `?int` | Seconds from the body or the `Retry-After` header |

| Result data | Meaning |
| --- | --- |
| decoded array | success |
| `null` | deletion / `204 No Content` / empty body — or failure (check `->error`) |

Wire format is passed through unchanged — no re-casing of server fields.

## Resources

Every method returns `CorvoNfse\Result`.

| Resource | Methods |
| --- | --- |
| `companies` | `create`, `get`, `update`, `remove`, `list`, `iterate` |
| `certificates` | `upload`, `list`, `remove` |
| `invoices` | `create`, `get`, `list`, `iterate`, `events`, `pdf`, `xml`, `downloadUrl`, `sendEmail` |
| `webhooks` | `list`, `iterate`, `create`, `remove`, `test` |
| `webhooks->deliveries` | `list`, `iterate`, `stats`, `retry` |
| `credits` | `balance`, `plans`, `history`, `iterateHistory` |
| `billing` | `plan`, `usage` |
| `health` | `live`, `ready` (2 public health checks, no `{data}` envelope) |

## Configuration

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `api_key` | `string` | `getenv('CORVO_API_KEY')` | Required. Sent as `X-API-Key` (never `Bearer`) |
| `base_url` | `string` | `getenv('CORVO_BASE_URL')` ?? `https://api.corvo.tech` | Self-hosted installs win |
| `prefix` | `string` | `v1` | For custom `API_PREFIX` deployments |
| `timeout` | `float` | `30.0` | Seconds |
| `allow_insecure_http` | `bool` | `false` | `http://` only allowed for loopback/private hosts unless enabled |
| `http_client` | `GuzzleHttp\ClientInterface` | built-in Guzzle 7 client | Bring your own handler stack / mocks |

Readonly wire types live in `CorvoNfse\Types` (`Company`, `Invoice`, `Webhook`, `QuotaStatus`, ...). Resources return the raw decoded payload; call `Type::fromArray($result->data)` whenever you want the typed view.

## The contract (in-depth)

Nine SDKs, one behavior. This SDK implements the same analysis as every other Corvo SDK, mirrored from the canonical Node SDK.

**Auth.** Every request carries `X-API-Key` (never `Authorization: Bearer`). Keys are scoped per tenant; the base URL defaults to `https://api.corvo.tech` and is overridable for self-hosted deployments. All routes live under the `v1` prefix.

**Response envelope.** Endpoints return one of three shapes, and the SDK unwraps them idiomatically: `{ "data": ... }` (single resource), `{ "data": { "props": ... } }` (resource behind a `props` wrapper), or no envelope (health checks, raw downloads). List endpoints return `{ "data": [...], "pagination": { "limit", "hasMore", "nextCursor" } }`. A `204 No Content` maps to an empty result.

**Errors never throw.** Network failures, timeouts, non-2xx responses, and malformed JSON all resolve to a typed error carrying `status`, `code`, `message`, `details`, `requestId`, and `retryAfter` — returned alongside (or as) the result, never raised. `code` falls back to `http_<status>` when the body doesn't carry one; transport-level failures use `network_error` / `timeout` / `invalid_json`. The only operation that raises is the `iterate` helper, and only on the first page's failure.

**Pagination.** List endpoints are cursor-paginated. The SDK exposes both a `list` (single page) and an `iterate`/stream helper that walks `nextCursor` until `hasMore` is false.

**Idempotency.** Invoice create and send-email accept an idempotency key, sent as the `Idempotency-Key` header so retries are safe.

**Webhooks.** Deliveries are signed with HMAC-SHA256 over the raw body. The SDK exposes a timing-safe `verifySignature(rawBody, header, secret)`.

**Surface.** Exactly 28 operations across companies, certificates, invoices, webhooks (incl. nested deliveries), credits, and billing — plus `health/live` and `health/ready`.

## Development

```bash
composer install
vendor/bin/phpunit    # unit + HMAC vector tests (contract tests skip without env)
vendor/bin/phpstan    # level 9
```

Contract tests run only when `CORVO_API_KEY` and `CORVO_BASE_URL` are set:

```bash
CORVO_API_KEY=corvo_test_xxx CORVO_BASE_URL=http://localhost:4000 vendor/bin/phpunit
```

Looking for a CLI? It ships with the Node SDK (`corvo-nfse-node`).

## License

MIT © Corvo Technology. See [LICENSE](LICENSE).
