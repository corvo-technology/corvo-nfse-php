<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of the `Invoice` entity.
 *
 * Snapshot fields (`emailSnapshot`, `customerSnapshot`, `serviceSnapshot`,
 * `taxSnapshot`) are passed through as raw arrays, exactly as the server
 * speaks them.
 */
final readonly class Invoice
{
    use Wire;

    /**
     * @param array<mixed> $emailSnapshot
     * @param array<mixed> $customerSnapshot
     * @param array<mixed> $serviceSnapshot
     * @param array<mixed> $taxSnapshot
     */
    public function __construct(
        public string $id,
        public string $companyId,
        public string $tenantId,
        public string $environment,
        public int $documentKeyVersion,
        /** @var array<mixed> */
        public array $emailSnapshot,
        public ?string $externalId,
        public ?string $idempotencyKey,
        public string $status,
        /** @var array<mixed> */
        public array $customerSnapshot,
        /** @var array<mixed> */
        public array $serviceSnapshot,
        /** @var array<mixed> */
        public array $taxSnapshot,
        public float $amount,
        public string $currency,
        public string $competenceDate,
        public ?string $dpsId,
        public ?string $dpsSeries,
        public ?int $dpsNumber,
        public ?string $accessKey,
        public ?int $nfseNumber,
        public ?string $fiscalProvider,
        public ?string $fiscalSchemaVersion,
        public ?string $issuedAt,
        public ?string $authorizedAt,
        public ?string $lastErrorCode,
        public ?string $lastErrorMessage,
        public ?string $lastErrorProvider,
        public ?string $documentsReadyAt,
        public ?string $lastEmailStatus,
        public ?string $lastEmailSentAt,
        public string $createdAt,
        public string $updatedAt,
        public string $documentsStatus,
        public ?string $emailStatus,
        public string $stage,
        /** When a scheduled invoice is released (ISO), or null for an immediate one. */
        public ?string $scheduledFor,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'id') ?? '',
            self::wireStr($data, 'companyId') ?? '',
            self::wireStr($data, 'tenantId') ?? '',
            self::wireStr($data, 'environment') ?? '',
            self::wireInt($data, 'documentKeyVersion') ?? 0,
            self::wireArr($data, 'emailSnapshot'),
            self::wireStr($data, 'externalId'),
            self::wireStr($data, 'idempotencyKey'),
            self::wireStr($data, 'status') ?? '',
            self::wireArr($data, 'customerSnapshot'),
            self::wireArr($data, 'serviceSnapshot'),
            self::wireArr($data, 'taxSnapshot'),
            self::wireFloat($data, 'amount') ?? 0.0,
            self::wireStr($data, 'currency') ?? '',
            self::wireStr($data, 'competenceDate') ?? '',
            self::wireStr($data, 'dpsId'),
            self::wireStr($data, 'dpsSeries'),
            self::wireInt($data, 'dpsNumber'),
            self::wireStr($data, 'accessKey'),
            self::wireInt($data, 'nfseNumber'),
            self::wireStr($data, 'fiscalProvider'),
            self::wireStr($data, 'fiscalSchemaVersion'),
            self::wireStr($data, 'issuedAt'),
            self::wireStr($data, 'authorizedAt'),
            self::wireStr($data, 'lastErrorCode'),
            self::wireStr($data, 'lastErrorMessage'),
            self::wireStr($data, 'lastErrorProvider'),
            self::wireStr($data, 'documentsReadyAt'),
            self::wireStr($data, 'lastEmailStatus'),
            self::wireStr($data, 'lastEmailSentAt'),
            self::wireStr($data, 'createdAt') ?? '',
            self::wireStr($data, 'updatedAt') ?? '',
            self::wireStr($data, 'documentsStatus') ?? '',
            self::wireStr($data, 'emailStatus'),
            self::wireStr($data, 'stage') ?? '',
            self::wireStr($data, 'scheduledFor'),
        );
    }
}
