<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Scheduled and recurring invoices. Create one with `invoices->create()` and
 * `options.type` `SCHEDULE` or `RECURRING`; the note is issued when the schedule runs.
 *
 * Schedules answer in snake_case (`next_run_on`, `anchor_on`). In lists, `nextCursor` is the
 * offset of the next page: pass it back untouched.
 */
final class Schedules
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/schedules
     *
     * @param array{status?: string, companyId?: string, limit?: int, cursor?: string} $params
     *        `status`: `active`, `paused`, `completed` or `cancelled`.
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function list(array $params = []): Result
    {
        return $this->http->list('/schedules', $params, self::plain());
    }

    /**
     * Walk every page of GET /v1/schedules.
     *
     * @param array{status?: string, companyId?: string, limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(array $params = []): \Generator
    {
        return $this->http->iterate('/schedules', $params, self::plain());
    }

    /**
     * GET /v1/schedules/{id}
     *
     * @return Result<mixed> `Schedule`.
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', self::path($id));
    }

    /**
     * PATCH /v1/schedules/{id} — applies to the next runs only.
     *
     * @param array{anchorOn?: string, frequency?: string, endsOn?: string|null, invoice?: array<string, mixed>} $params
     *
     * @return Result<mixed> `Schedule`.
     */
    public function update(string $id, array $params): Result
    {
        return $this->http->request('PATCH', self::path($id), json: $params);
    }

    /**
     * POST /v1/schedules/{id}/pause
     *
     * @return Result<mixed> `Schedule`.
     */
    public function pause(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/pause');
    }

    /**
     * POST /v1/schedules/{id}/resume — resumes at the next occurrence.
     *
     * @return Result<mixed> `Schedule`.
     */
    public function resume(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/resume');
    }

    /**
     * POST /v1/schedules/{id}/cancel — final.
     *
     * @return Result<mixed> `Schedule`.
     */
    public function cancel(string $id): Result
    {
        return $this->http->request('POST', self::path($id) . '/cancel');
    }

    /**
     * GET /v1/schedules/{id}/runs — every attempt to run the schedule, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function listRuns(string $id, array $params = []): Result
    {
        return $this->http->list(self::path($id) . '/runs', $params, self::plain());
    }

    private static function path(string $id): string
    {
        return '/schedules/' . rawurlencode($id);
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
