<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Shape returned by every list operation: `{data, pagination}`.
 *
 * Consumers normally stay with the raw array the SDK passes through; this
 * class is an optional typed view over it.
 */
final readonly class Paginated
{
    use Wire;

    /**
     * @param list<mixed> $data
     */
    public function __construct(
        /** @var list<mixed> */
        public array $data,
        public Pagination $pagination,
    ) {
    }

    /**
     * @param array<string, mixed> $page the raw `{data, pagination}` envelope.
     */
    public static function fromArray(array $page): self
    {
        $items = self::wireArr($page, 'data');

        return new self(
            array_values($items),
            Pagination::fromArray(self::wireArr($page, 'pagination')),
        );
    }
}
