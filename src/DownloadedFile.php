<?php

declare(strict_types=1);

namespace CorvoNfse;

/**
 * A binary download (invoice PDF/XML): raw bytes plus the response's
 * `Content-Type` and `Content-Disposition` filename when present.
 */
final readonly class DownloadedFile
{
    public function __construct(
        public string $content,
        public ?string $mimeType = null,
        public ?string $fileName = null,
    ) {
    }
}
