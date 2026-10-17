<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of GET /billing/usage: live emission quota.
 */
final readonly class QuotaStatus
{
    use Wire;

    public function __construct(
        public bool $canEmit,
        public float $creditsRemaining,
        public float $creditsUsed,
        public float $creditsReserved,
        public float $monthlyAllowance,
        public float $dailyUsage,
        public ?int $dailyLimit,
        public int $activeCompanies,
        public int $maxActiveCompanies,
        public ?string $periodStart,
        public ?string $periodEnd,
        /** Set when `canEmit` is false: no_credits, daily_limit_exceeded, company_limit_exceeded or plan_blocked. */
        public ?string $reason,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireBool($data, 'canEmit') ?? false,
            self::wireFloat($data, 'creditsRemaining') ?? 0.0,
            self::wireFloat($data, 'creditsUsed') ?? 0.0,
            self::wireFloat($data, 'creditsReserved') ?? 0.0,
            self::wireFloat($data, 'monthlyAllowance') ?? 0.0,
            self::wireFloat($data, 'dailyUsage') ?? 0.0,
            self::wireInt($data, 'dailyLimit'),
            self::wireInt($data, 'activeCompanies') ?? 0,
            self::wireInt($data, 'maxActiveCompanies') ?? 0,
            self::wireStr($data, 'periodStart'),
            self::wireStr($data, 'periodEnd'),
            self::wireStr($data, 'reason'),
        );
    }
}
