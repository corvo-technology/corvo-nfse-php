<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of the `Certificate` entity (A1 .p12 attached to a company).
 */
final readonly class Certificate
{
    use Wire;

    public function __construct(
        public string $id,
        public string $companyId,
        public string $serialNumber,
        public string $issuer,
        public string $subject,
        public string $fingerprint,
        public string $validFrom,
        public string $validUntil,
        public string $status,
        public string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'companyId') ?? '',
            self::wireStr($data, 'serialNumber') ?? '',
            self::wireStr($data, 'issuer') ?? '',
            self::wireStr($data, 'subject') ?? '',
            self::wireStr($data, 'fingerprint') ?? '',
            self::wireStr($data, 'validFrom') ?? '',
            self::wireStr($data, 'validUntil') ?? '',
            self::wireStr($data, 'status') ?? '',
            self::wireStr($data, 'createdAt') ?? '',
        );
    }
}
