<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of a webhook endpoint as returned ONCE by POST /webhooks —
 * the only response that includes the signing `secret`.
 */
final readonly class WebhookWithSecret extends Webhook
{
    public function __construct(
        string $id,
        string $url,
        /** @var list<string> */
        array $events,
        bool $active,
        string $createdAt,
        string $updatedAt,
        public string $secret,
    ) {
        parent::__construct($id, $url, $events, $active, $createdAt, $updatedAt);
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
            self::wireStr($data, 'secret') ?? '',
        );
    }
}
