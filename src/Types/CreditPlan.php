<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a purchasable credit plan (GET /credits/plans).
 */
final readonly class CreditPlan
{
    use Wire;

    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $description,
        public int $dailyLimit,
        public int $maxActiveCompanies,
        public int $creditUnitPriceCents,
        public int $throughputRps,
        public bool $includesWebhooks,
        public bool $includesXmlDownload,
        public bool $includesPdfDownload,
        public bool $includesApiKeys,
        public string $supportTier,
        public bool $active,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'slug') ?? '',
            self::wireStr($data, 'name') ?? '',
            self::wireStr($data, 'description') ?? '',
            self::wireInt($data, 'dailyLimit') ?? 0,
            self::wireInt($data, 'maxActiveCompanies') ?? 0,
            self::wireInt($data, 'creditUnitPriceCents') ?? 0,
            self::wireInt($data, 'throughputRps') ?? 0,
            self::wireBool($data, 'includesWebhooks') ?? false,
            self::wireBool($data, 'includesXmlDownload') ?? false,
            self::wireBool($data, 'includesPdfDownload') ?? false,
            self::wireBool($data, 'includesApiKeys') ?? false,
            self::wireStr($data, 'supportTier') ?? '',
            self::wireBool($data, 'active') ?? false,
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'updatedAt') ?? '',
        );
    }
}
