<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a credit ledger entry (GET /credits/history).
 */
final readonly class CreditTransaction
{
    use Wire;

    public function __construct(
        public string $id,
        public string $type,
        public float $amount,
        public float $creditDelta,
        public float $balanceAfter,
        public string $environment,
        public string $source,
        public ?string $referenceType,
        public ?string $referenceId,
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
            self::wireStr($data, 'type') ?? '',
            self::wireFloat($data, 'amount') ?? 0.0,
            self::wireFloat($data, 'creditDelta') ?? 0.0,
            self::wireFloat($data, 'balanceAfter') ?? 0.0,
            self::wireStr($data, 'environment') ?? '',
            self::wireStr($data, 'source') ?? '',
            self::wireStr($data, 'referenceType'),
            self::wireStr($data, 'referenceId'),
            self::wireStr($data, 'createdAt') ?? '',
        );
    }
}
