<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a spreadsheet import (snake_case on the wire).
 */
final readonly class ImportJob
{
    use Wire;

    public function __construct(
        public ?string $company_id,
        /** @var array<mixed> */
        public array $counters,
        public string $created_at,
        public ?string $emit_finished_at,
        public ?string $emit_started_at,
        public ?string $error_code,
        public string $file_name,
        public string $id,
        public ?int $rows_expected,
        public string $status,
        /** @var array<mixed> */
        public array $unknown_columns,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'company_id'),
            self::wireArr($data, 'counters'),
            self::wireStr($data, 'created_at') ?? '',
            self::wireStr($data, 'emit_finished_at'),
            self::wireStr($data, 'emit_started_at'),
            self::wireStr($data, 'error_code'),
            self::wireStr($data, 'file_name') ?? '',
            self::wireStr($data, 'id') ?? '',
            self::wireInt($data, 'rows_expected'),
            self::wireStr($data, 'status') ?? '',
            self::wireArr($data, 'unknown_columns'),
        );
    }
}
