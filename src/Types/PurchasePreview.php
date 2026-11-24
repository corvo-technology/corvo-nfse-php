<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of price of a one-off purchase of notes (`GET /credits/purchase/preview`).
 */
final readonly class PurchasePreview
{
    use Wire;

    public function __construct(
        public int $totalCents,
        public int $unitPriceCents,
        public int $units,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'totalCents') ?? 0,
            self::wireInt($data, 'unitPriceCents') ?? 0,
            self::wireInt($data, 'units') ?? 0,
        );
    }
}
