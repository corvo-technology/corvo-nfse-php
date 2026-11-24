<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of accountant e-mail settings (`GET /companies/{id}/accountant-package`).
 */
final readonly class AccountantSettings
{
    use Wire;

    public function __construct(
        public ?string $email,
        public ?string $lastSentMonth,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'email'),
            self::wireStr($data, 'lastSentMonth'),
        );
    }
}
