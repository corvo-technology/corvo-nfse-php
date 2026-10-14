<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Wire mirror of POST /invoices/{id}/download-url: a short-lived signed URL.
 */
final readonly class InvoiceDownloadUrl
{
    use Wire;

    public function __construct(
        public string $downloadUrl,
        public string $expiresAt,
        public string $fileName,
        public string $mimeType,
    ) {
    }

    /**
     * @param array<string, mixed> $data raw entity as returned by the API.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::wireStr($data, 'downloadUrl') ?? '',
            self::wireStr($data, 'expiresAt') ?? '',
            self::wireStr($data, 'fileName') ?? '',
            self::wireStr($data, 'mimeType') ?? '',
        );
    }
}
