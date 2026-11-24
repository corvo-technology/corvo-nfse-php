<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of one attempt to run a schedule (snake_case on the wire).
 */
final readonly class ScheduleRun
{
    use Wire;

    public function __construct(
        public int $attempt,
        public string $created_at,
        public ?string $error_code,
        public ?string $error_message,
        public string $id,
        public ?string $invoice_id,
        public string $occurrence_on,
        public string $outcome,
        public string $ran_on,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireInt($data, 'attempt') ?? 0,
            self::wireStr($data, 'created_at') ?? '',
            self::wireStr($data, 'error_code'),
            self::wireStr($data, 'error_message'),
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'invoice_id'),
            self::wireStr($data, 'occurrence_on') ?? '',
            self::wireStr($data, 'outcome') ?? '',
            self::wireStr($data, 'ran_on') ?? '',
        );
    }
}
