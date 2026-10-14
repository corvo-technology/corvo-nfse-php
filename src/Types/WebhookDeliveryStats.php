<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of GET /webhooks/deliveries/stats.
 */
final readonly class WebhookDeliveryStats
{
    use Wire;

    public function __construct(
        public int $total,
        public int $sent,
        public int $failed,
        public int $pending,
        public int $delayed,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'total') ?? 0,
            self::wireInt($data, 'sent') ?? 0,
            self::wireInt($data, 'failed') ?? 0,
            self::wireInt($data, 'pending') ?? 0,
            self::wireInt($data, 'delayed') ?? 0,
        );
    }
}
