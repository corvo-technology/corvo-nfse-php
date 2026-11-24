<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a favorite service of a company.
 */
final readonly class ServiceFavorite
{
    use Wire;

    public function __construct(
        public ?int $amountCents,
        public string $companyId,
        public string $createdAt,
        public string $description,
        public string $id,
        public string $label,
        public ?string $municipalityCode,
        public string $serviceCode,
        public string $tenantId,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'amountCents'),
            self::wireStr($data, 'companyId') ?? '',
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'description') ?? '',
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'label') ?? '',
            self::wireStr($data, 'municipalityCode'),
            self::wireStr($data, 'serviceCode') ?? '',
            self::wireStr($data, 'tenantId') ?? '',
        );
    }
}
