<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of one row of an import (snake_case on the wire).
 */
final readonly class ImportRow
{
    use Wire;

    public function __construct(
        /** @var array<mixed> */
        public array $data,
        public ?string $error_code,
        public ?string $error_message,
        public ?string $invoice_id,
        public int $line,
        public string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireArr($data, 'data'),
            self::wireStr($data, 'error_code'),
            self::wireStr($data, 'error_message'),
            self::wireStr($data, 'invoice_id'),
            self::wireInt($data, 'line') ?? 0,
            self::wireStr($data, 'status') ?? '',
        );
    }
}
