<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a customer saved in the address book (`GET /customers`).
 */
final readonly class SavedCustomer
{
    use Wire;

    public function __construct(
        public string $createdAt,
        public ?string $email,
        public string $lastUsedAt,
        public string $name,
        public string $taxId,
        public string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'email'),
            self::wireStr($data, 'lastUsedAt') ?? '',
            self::wireStr($data, 'name') ?? '',
            self::wireStr($data, 'taxId') ?? '',
            self::wireStr($data, 'updatedAt') ?? '',
        );
    }
}
