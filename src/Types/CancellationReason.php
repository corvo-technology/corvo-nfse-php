<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Reason sent to `POST /v1/invoices/{id}/cancel` and echoed back in
 * `Invoice::$cancellationReason`.
 *
 * {@see \CorvoNfse\Resources\Invoices::cancel()} also accepts the raw wire
 * string. When reading an invoice, use `CancellationReason::tryFrom()` so a
 * reason added by the server later does not throw.
 */
enum CancellationReason: string
{
    /** The invoice was issued with wrong data. */
    case IssueError = 'issue_error';

    /** The service was not provided. */
    case ServiceNotProvided = 'service_not_provided';

    /** Any other reason; explain it in the description. */
    case Other = 'other';
}
