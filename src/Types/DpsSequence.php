<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of DPS numbering of a company.
 */
final readonly class DpsSequence
{
    use Wire;

    public function __construct(
        public int $lastNumber,
        public string $series,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'lastNumber') ?? 0,
            self::wireStr($data, 'series') ?? '',
        );
    }
}
