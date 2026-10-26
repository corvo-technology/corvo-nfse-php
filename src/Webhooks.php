<?php

declare(strict_types=1);

namespace CorvoNfse;

/**
 * Webhook helpers: signature verification and the supported event catalog.
 */
final class Webhooks
{
    /**
     * All events a webhook endpoint can subscribe to.
     *
     * @var list<string>
     */
    public const WEBHOOK_EVENTS = [
        'nfse.invoice.authorized',
        'nfse.invoice.rejected',
        'nfse.invoice.failed',
        'nfse.invoice.reconciliation_required',
        'nfse.invoice.manual_review',
        'nfse.invoice.documents_ready',
        'nfse.invoice.email_sent',
        'nfse.invoice.email_failed',
        'nfse.invoice.fiscal_discrepancy',
        'nfse.invoice.cancelled',
        'nfse.invoice.cancellation_rejected',
    ];

    private function __construct()
    {
    }

    /**
     * Verify an `X-Corvo-Signature`-style header (`sha256=<hex>`) against the
     * RAW request body using HMAC-SHA256. Comparison is constant-time.
     *
     * Use the raw bytes the server sent — re-serialized JSON will never match.
     */
    public static function verifySignature(string $rawBody, ?string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === null || $secret === '') {
            return false;
        }

        if (preg_match('/^sha256=([0-9a-f]{64})$/', trim($signatureHeader), $matches) !== 1) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $matches[1]);
    }
}
