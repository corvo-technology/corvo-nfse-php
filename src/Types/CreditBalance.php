<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of GET /credits: tenant credit balance and overage counters.
 */
final readonly class CreditBalance
{
    use Wire;

    public function __construct(
        public string $tenantId,
        public float $balance,
        public int $overageUnits,
        public int $overageAmountCents,
        public ?int $hardLimit,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'tenantId') ?? '',
            self::wireFloat($data, 'balance') ?? 0.0,
            self::wireInt($data, 'overageUnits') ?? 0,
            self::wireInt($data, 'overageAmountCents') ?? 0,
            self::wireInt($data, 'hardLimit'),
        );
    }
}
