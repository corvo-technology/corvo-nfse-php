<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of registration data of a CNPJ (`GET /cnpj/{cnpj}`).
 */
final readonly class CnpjProfile
{
    use Wire;

    public function __construct(
        public bool $active,
        /** @var array<mixed> */
        public array $address,
        public string $legalName,
        public ?bool $mei,
        public ?string $municipalityCode,
        public ?bool $simples,
        public string $situation,
        public string $tradeName,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireBool($data, 'active') ?? false,
            self::wireArr($data, 'address'),
            self::wireStr($data, 'legalName') ?? '',
            self::wireBool($data, 'mei'),
            self::wireStr($data, 'municipalityCode'),
            self::wireBool($data, 'simples'),
            self::wireStr($data, 'situation') ?? '',
            self::wireStr($data, 'tradeName') ?? '',
        );
    }
}
