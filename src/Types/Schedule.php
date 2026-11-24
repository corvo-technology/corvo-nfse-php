<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a scheduled or recurring invoice (snake_case on the wire).
 */
final readonly class Schedule
{
    use Wire;

    public function __construct(
        public string $anchor_on,
        public int $attempts,
        public string $company_id,
        public string $created_at,
        public ?string $ends_on,
        public ?string $frequency,
        public string $id,
        /** @var array<mixed> */
        public array $invoice,
        public string $kind,
        public ?string $last_run_on,
        public ?string $next_run_on,
        public ?string $pause_reason,
        public string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'anchor_on') ?? '',
            self::wireInt($data, 'attempts') ?? 0,
            self::wireStr($data, 'company_id') ?? '',
            self::wireStr($data, 'created_at') ?? '',
            self::wireStr($data, 'ends_on'),
            self::wireStr($data, 'frequency'),
            self::wireStr($data, 'id') ?? '',
            self::wireArr($data, 'invoice'),
            self::wireStr($data, 'kind') ?? '',
            self::wireStr($data, 'last_run_on'),
            self::wireStr($data, 'next_run_on'),
            self::wireStr($data, 'pause_reason'),
            self::wireStr($data, 'status') ?? '',
        );
    }

    /**
     * `POST /invoices` answers with an `Invoice` for `IMMEDIATE` and with a schedule
     * (`kind` and `anchor_on`) for `SCHEDULE` and `RECURRING`: use this to tell them apart.
     *
     * @param array<string, mixed> $data the `data` of the answer.
     */
    public static function isSchedule(array $data): bool
    {
        return isset($data['kind'], $data['anchor_on']);
    }
}
