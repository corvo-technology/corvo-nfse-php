<?php

declare(strict_types=1);

namespace CorvoNfse\Types;

/**
 * Defensive extractors used by every wire type's `fromArray()`.
 *
 * The server's JSON is passed through unchanged; these helpers only coerce
 * scalars into the declared PHP types and default missing fields, so a schema
 * drift never raises a TypeError inside the SDK.
 */
trait Wire
{
    /** @param array<mixed> $data */
    protected static function wireStr(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param array<mixed> $data */
    protected static function wireInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /** @param array<mixed> $data */
    protected static function wireFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /** @param array<mixed> $data */
    protected static function wireBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    protected static function wireArr(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
