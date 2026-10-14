<?php

declare(strict_types=1);

namespace CorvoNfse;

/**
 * Result of a single API call. Exactly one of `$data` / `$error` is set:
 *
 *   - success: `new Result($data, null)`
 *   - failure: `new Result(null, $error)`
 *
 * The SDK never throws for API, network or timeout failures — it returns a
 * Result carrying a {@see CorvoApiError} instead. The single documented
 * exception is `iterate()`, which raises the error on a page failure because
 * a generator has no channel to return a Result.
 *
 * @template T
 */
final readonly class Result
{
    /**
     * @param T $data unwrapped payload on success; null on failure
     *        (deletions/`204 No Content` also yield null on success).
     * @param CorvoApiError|null $error null on success, the error on failure.
     */
    public function __construct(
        public mixed $data,
        public ?CorvoApiError $error,
    ) {
    }

    public function isOk(): bool
    {
        return $this->error === null;
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }
}
