<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a credit ledger entry (GET /credits/history).
 *
 * `amount` is in credits (not BRL); fields ending in `Cents` are in centavos.
 */
final readonly class CreditTransaction
{
    use Wire;

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $type,
        public float $amount,
        public ?string $description,
        /** `subscription`, `avulso` or `bonus`. */
        public ?string $source,
        public ?int $amountBrlCents,
        public ?int $unitPriceCentsApplied,
        public ?string $planIdSnapshot,
        public ?string $reservedInvoiceId,
        public ?string $periodId,
        public ?bool $isReservation,
        public ?string $relatedTransactionId,
        public string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'tenantId') ?? '',
            self::wireStr($data, 'type') ?? '',
            self::wireFloat($data, 'amount') ?? 0.0,
            self::wireStr($data, 'description'),
            self::wireStr($data, 'source'),
            self::wireInt($data, 'amountBrlCents'),
            self::wireInt($data, 'unitPriceCentsApplied'),
            self::wireStr($data, 'planIdSnapshot'),
            self::wireStr($data, 'reservedInvoiceId'),
            self::wireStr($data, 'periodId'),
            self::wireBool($data, 'isReservation'),
            self::wireStr($data, 'relatedTransactionId'),
            self::wireStr($data, 'createdAt') ?? '',
        );
    }
}
