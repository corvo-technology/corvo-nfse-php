<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of one attempt to send the monthly package to the accountant.
 */
final readonly class AccountantSend
{
    use Wire;

    public function __construct(
        public string $createdAt,
        public string $id,
        public int $invoiceCount,
        public string $month,
        public string $recipient,
        public string $status,
        public int $totalCents,
        public string $trigger,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'id') ?? '',
            self::wireInt($data, 'invoiceCount') ?? 0,
            self::wireStr($data, 'month') ?? '',
            self::wireStr($data, 'recipient') ?? '',
            self::wireStr($data, 'status') ?? '',
            self::wireInt($data, 'totalCents') ?? 0,
            self::wireStr($data, 'trigger') ?? '',
        );
    }
}
