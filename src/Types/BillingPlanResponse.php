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
     * @param array<mixed> $plan raw plan block: `{slug, name, monthly_allowance,
     *        daily_limit, max_active_companies, monthly_price_cents}` (snake_case). `slug` is
     *        `free`, `self-hosted`, `solo`, `equipe`, `empresa` or `escala`: treat it as open.
     * @param array<mixed> $subscription raw subscription block: `{status,
     *        paid_until, cancel_at_period_end, scheduled_plan, blocked, stripe_portal_available,
     *        stripe_subscription, pix_subscription}` (`pix_subscription` is an object or null).
     * @param array<mixed> $usage raw usage block: `active_companies`, `avulso_balance`,
     *        `credits_remaining`, `credits_reserved`, `credits_used`, `daily_limit`, `daily_usage`,
     *        `hard_limit`, `max_active_companies`, `monthly_allowance`, `period_end`, `period_start`.
     */
    public function __construct(
        /** @var array<mixed> */
        public array $plan,
        /** @var array<mixed> */
        public array $subscription,
        /** @var array<mixed> */
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
