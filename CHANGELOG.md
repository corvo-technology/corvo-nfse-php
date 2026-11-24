# Changelog

## 0.3.0

Follows the public API contract 0.3.0 ([openapi.json](https://corvo.tech/openapi.json), 92 operations). The contract is vendored at `spec/openapi.json` and a test checks that every operation has an SDK method.

### Breaking

- **Plan slugs.** `developer`, `growth`, `scale` and `volume` are gone. The slugs are now `free`, `solo`, `equipe`, `empresa` and `escala` (plus `self-hosted` on a self-hosted instance). Treat the slug as an open string.
- **`invoices->create()` with `options.type` `SCHEDULE`** answers with the schedule that was created (snake_case fields), not an invoice. `RECURRING` is new and behaves the same way. Use `Types\Schedule::isSchedule($result->data)` to tell the two answers apart, and `schedules->listRuns()` to follow the runs.
- **`credits->balance()`** no longer has `overageUnits` and `overageAmountCents` (there is no overage billing). New fields: `planRemaining` and `avulsoBalance`; `balance` includes the loose notes. `Types\CreditBalance` lost `tenantId`, `overageUnits` and `overageAmountCents` and gained `planRemaining` and `avulsoBalance`; `balance` and `hardLimit` are now `int`.
- **`billing->plan()`**: `usage.overage_units` and `usage.overage_amount_cents` are gone; new `usage.avulso_balance` and `subscription.stripe_portal_available`, `stripe_subscription`, `pix_subscription`. A workspace without a plan gets a 404 with `code: "NO_PLAN_ASSIGNED"`.
- **Credit purchase preview** is now `GET /credits/purchase/preview?units=N`: `credits->previewPurchase($units)`.
- **Webhooks and environments.** A webhook created with a key belongs to that key's environment. A `corvo_test_` key can no longer reach live webhooks, and webhook URLs that resolve to private networks are refused by the API.
- **`invoices->sendEmail()`** has a new last parameter, `$email`, to send to another address. Positional calls keep working.

### Added

- Customers: `customers->search/iterate/get/update/remove`, `cnpj->lookup`, `serviceFavorites->list/create/remove`.
- Accountant package: `accountantPackage->get/update/send/listSends`, `invoicePackage->summary/download` (monthly ZIP).
- Invoices: `substitute` (with idempotency key), `overview`, `review`, `list` filter `customerTaxId`, `sendEmail` to another address.
- Schedules: `schedules->list/iterate/get/update/pause/resume/cancel/listRuns`.
- Imports: `imports->start` (multipart), `list`, `get`, `emit`, `listRows`, `iterateRows`, `updateRow`, `discard`, `downloadTemplate`, `downloadErrors`.
- Company setup: `fiscalProfiles->list/upsert/approve`, `serviceTaxProfiles->list/create/update/remove/approve`, `dpsSequence->get/set`.
- Integrations: `integrations->listProviders`, `integrationConnections->*` (create, list, get, validate, updateCredentials, activate, pause, disconnect, listRules, replaceRules, listEvents, listOperations), `integrationOperations->completeCustomer/retry`.
- `credits->previewPurchase`, `fiscalPortal->status`.
- Wire types: `SavedCustomer`, `CnpjProfile`, `ServiceFavorite`, `AccountantSettings`, `AccountantSend`, `PackageSummary`, `DpsSequence`, `PurchasePreview`, `PortalStatus`, `ImportJob`, `ImportRow`, `Schedule`, `ScheduleRun`.
- Webhook event `nfse.invoice.taker_rejected` (12 events, same as the contract).
- `User-Agent: corvo-nfse-php/<version>` on every request.
- Release workflow: a `v*` tag runs the checks and creates the GitHub release.

### Changed

- `Types\Invoice` has `substitutesInvoiceId`, `substitutionDescription` and `portalFlag`; `Types\InvoiceEvent` has `source`, `portalEvent` and `portalNsu`; `Types\Webhook` has `environment`.
- Documentation of the invoice fields: `documentsReadyAt`, `documentsStatus`, `emailStatus`, `stage`, `lastEmailStatus`, `lastEmailSentAt`. The ten invoice statuses are the ones of the contract.
- Tests: every operation of the contract is called against a mocked HTTP layer (method, path, query, body, `x-api-key`, `Idempotency-Key`).

## 0.2.0

First version.
