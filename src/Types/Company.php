<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of the `Company` entity (verified API contract).
 */
final readonly class Company
{
    use Wire;

    /**
     * @param list<string> $setupGaps
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $legalName,
        public ?string $tradeName,
        public string $taxId,
        public ?string $municipalReg,
        public ?string $municipalityCode,
        public string $taxRegime,
        public bool $simpleNational,
        public ?string $specialRegime,
        public ?string $addressStreet,
        public ?string $addressNumber,
        public ?string $addressComplement,
        public ?string $addressDistrict,
        public string $addressCity,
        public string $addressState,
        public string $addressZip,
        public string $fiscalSchemaVersion,
        public string $environment,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
        public ?string $deletedAt,
        /** @var list<string> */
        public array $setupGaps,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'tenantId') ?? '',
            self::wireStr($data, 'legalName') ?? '',
            self::wireStr($data, 'tradeName'),
            self::wireStr($data, 'taxId') ?? '',
            self::wireStr($data, 'municipalReg'),
            self::wireStr($data, 'municipalityCode'),
            self::wireStr($data, 'taxRegime') ?? '',
            self::wireBool($data, 'simpleNational') ?? false,
            self::wireStr($data, 'specialRegime'),
            self::wireStr($data, 'addressStreet'),
            self::wireStr($data, 'addressNumber'),
            self::wireStr($data, 'addressComplement'),
            self::wireStr($data, 'addressDistrict'),
            self::wireStr($data, 'addressCity') ?? '',
            self::wireStr($data, 'addressState') ?? '',
            self::wireStr($data, 'addressZip') ?? '',
            self::wireStr($data, 'fiscalSchemaVersion') ?? '',
            self::wireStr($data, 'environment') ?? '',
            self::wireStr($data, 'status') ?? '',
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'updatedAt') ?? '',
            self::wireStr($data, 'deletedAt'),
            array_values(array_map(static fn (mixed $item): string => (string) $item, self::wireArr($data, 'setupGaps'))),
        );
    }
}
