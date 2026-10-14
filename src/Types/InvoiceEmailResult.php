<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of POST /invoices/{id}/email.
 */
final readonly class InvoiceEmailResult
{
    use Wire;

    public function __construct(
        public string $emailId,
        public string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'emailId') ?? '',
            self::wireStr($data, 'status') ?? '',
        );
    }
}
