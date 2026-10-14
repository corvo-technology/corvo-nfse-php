<?php

declare(strict_types=1);

namespace CorvoNfse;

/**
 * Typed failure returned by every SDK operation, mirroring the Node SDK's
 * `CorvoApiError` fields: stable `code`, HTTP `status`, field-level `details`,
 * `requestId` and `retryAfter`.
 *
 * Extends {@see \Exception} so `iterate()` can raise it on a page failure
 * while it is still usable as a plain value inside {@see Result}.
 */
final class CorvoApiError extends \Exception
{
    /**
     * @param int $status HTTP status (0 for network/timeout failures).
     * @param string $code stable server code, or `http_<status>`,
     *        `invalid_json`, `network_error`, `timeout`.
     * @param string $message human-readable message.
     * @param array<mixed> $details field-level validation details, when the
     *        server sends them: typically `list<array{field, code, message}>`.
     * @param string|null $requestId `requestId` body field or `X-Request-Id` header.
     * @param int|null $retryAfter seconds from the body or the `Retry-After` header.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        string $message,
        public readonly array $details = [],
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
