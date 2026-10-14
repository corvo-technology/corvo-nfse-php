<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a webhook endpoint configuration.
 */
readonly class Webhook
{
    use Wire;

    public function __construct(
        public string $id,
        public string $url,
        /** @var list<string> */
        public array $events,
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
            self::wireStr($data, 'url') ?? '',
            array_values(array_map('strval', self::wireArr($data, 'events'))),
            self::wireBool($data, 'active') ?? false,
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'updatedAt') ?? '',
        );
    }
}
