<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of GET /credits (contract 0.3.0): `balance` is the plan notes left plus the loose
 * notes bought separately (`avulsoBalance`); there is no overage billing.
 */
final readonly class CreditBalance
{
    use Wire;

    public function __construct(
        public int $balance,
        public int $planRemaining,
        public int $avulsoBalance,
        public int $hardLimit,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'balance') ?? 0,
            self::wireInt($data, 'planRemaining') ?? 0,
            self::wireInt($data, 'avulsoBalance') ?? 0,
            self::wireInt($data, 'hardLimit') ?? 0,
        );
    }
}
