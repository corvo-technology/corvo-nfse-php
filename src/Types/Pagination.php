<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Cursor pagination block of a list response: `{limit, hasMore, nextCursor}`.
 * The cursor is opaque base64url; `nextCursor` is null when `hasMore` is false.
 */
final readonly class Pagination
{
    use Wire;

    public function __construct(
        public int $limit,
        public bool $hasMore,
        public ?string $nextCursor,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'limit') ?? 0,
            self::wireBool($data, 'hasMore') ?? false,
            self::wireStr($data, 'nextCursor'),
        );
    }
}
