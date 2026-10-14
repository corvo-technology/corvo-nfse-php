<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of an invoice status-transition event.
 */
final readonly class InvoiceEvent
{
    use Wire;

    public function __construct(
        public string $id,
        public string $invoiceId,
        public ?string $fromStatus,
        public string $toStatus,
        public ?string $reason,
        public string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'invoiceId') ?? '',
            self::wireStr($data, 'fromStatus'),
            self::wireStr($data, 'toStatus') ?? '',
            self::wireStr($data, 'reason'),
            self::wireStr($data, 'createdAt') ?? '',
        );
    }
}
