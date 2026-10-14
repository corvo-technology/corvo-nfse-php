<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of GET /billing/plan.
 *
 * NOTE: unlike every other endpoint, the three blocks below keep the server's
 * snake_case keys (`monthly_allowance`, `paid_until`, ...) — wire format is
 * passed through unchanged.
 */
final readonly class BillingPlanResponse
{
    use Wire;

    /**
     * @param array{
     *     slug: string,
     *     name: string,
     *     monthly_allowance: int,
     *     daily_limit: int|null,
     *     max_active_companies: int,
     *     monthly_price_cents: int
     * } $plan
     * @param array{
     *     status: string,
     *     paid_until: string|null,
     *     cancel_at_period_end: bool,
     *     scheduled_plan: string|null,
     *     blocked: bool
     * } $subscription
     * @param array<string, int|float|string|null> $usage
     */
    public function __construct(
        public array $plan,
        public array $subscription,
        public array $usage,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireArr($data, 'plan'),
            self::wireArr($data, 'subscription'),
            self::wireArr($data, 'usage'),
        );
    }
}
