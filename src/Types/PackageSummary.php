<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of count and total of a monthly invoice package.
 */
final readonly class PackageSummary
{
    use Wire;

    public function __construct(
        public string $companyName,
        public string $companyTaxId,
        public int $count,
        public int $limit,
        public string $month,
        /** Decimal string such as "1234.50" (exact; do not cast to float for money). */
        public string $total,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'companyName') ?? '',
            self::wireStr($data, 'companyTaxId') ?? '',
            self::wireInt($data, 'count') ?? 0,
            self::wireInt($data, 'limit') ?? 0,
            self::wireStr($data, 'month') ?? '',
            self::wireDecimalString($data, 'total'),
        );
    }
}
