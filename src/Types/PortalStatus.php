<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of status of the national NFS-e portal.
 */
final readonly class PortalStatus
{
    use Wire;

    public function __construct(
        public bool $available,
        public string $environment,
        public ?string $retryAt,
        public ?string $since,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireBool($data, 'available') ?? false,
            self::wireStr($data, 'environment') ?? '',
            self::wireStr($data, 'retryAt'),
            self::wireStr($data, 'since'),
        );
    }
}
