<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a single webhook delivery attempt.
 */
final readonly class WebhookDelivery
{
    use Wire;

    public function __construct(
        public string $id,
        public string $tenantId,
        public string $webhookConfigId,
        public string $eventType,
        public string $environment,
        public string $url,
        /** @var array<string, mixed> */
        public array $payload,
        public ?int $statusCode,
        public mixed $responseBody,
        public ?string $errorMessage,
        public string $status,
        public ?int $durationMs,
        public int $retryCount,
        public ?string $nextRetryAt,
        public ?string $lastError,
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
            self::wireStr($data, 'webhookConfigId') ?? '',
            self::wireStr($data, 'eventType') ?? '',
            self::wireStr($data, 'environment') ?? '',
            self::wireStr($data, 'url') ?? '',
            self::wireArr($data, 'payload'),
            self::wireInt($data, 'statusCode'),
            $data['responseBody'] ?? null,
            self::wireStr($data, 'errorMessage'),
            self::wireStr($data, 'status') ?? '',
            self::wireInt($data, 'durationMs'),
            self::wireInt($data, 'retryCount') ?? 0,
            self::wireStr($data, 'nextRetryAt'),
            self::wireStr($data, 'lastError'),
            self::wireStr($data, 'createdAt') ?? '',
        );
    }
}
