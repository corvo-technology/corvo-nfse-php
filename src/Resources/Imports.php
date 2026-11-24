<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Spreadsheet imports: upload a CSV/XLSX, review the rows, emit the valid ones.
 *
 * Imports answer in snake_case (`company_id`, `unknown_columns`).
 */
final class Imports
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * POST /v1/imports — multipart upload of a `.csv` or `.xlsx` (up to 2,000 rows and 10 MB).
     * Answers 202: the file is read in the background; follow it with {@see get()}.
     *
     * @param string $file raw bytes of the spreadsheet.
     * @param string|null $companyId company that issues the rows without a CNPJ value.
     *
     * @return Result<mixed> `ImportStart`: `{id, status, duplicate_of}`.
     */
    public function start(string $file, ?string $companyId = null, ?string $fileName = null): Result
    {
        $parts = [];
        if ($companyId !== null) {
            $parts[] = ['name' => 'companyId', 'contents' => $companyId];
        }
        $parts[] = [
            'name' => 'file',
            'contents' => $file,
            'filename' => $fileName ?? 'import.csv',
        ];

        return $this->http->requestMultipart('POST', '/imports', $parts);
    }

    /**
     * GET /v1/imports — recent imports, newest first (at most 20). `data` is a plain list.
     *
     * @return Result<mixed> list of `ImportJob`.
     */
    public function list(?int $limit = null): Result
    {
        return $this->http->request('GET', '/imports', query: ['limit' => $limit]);
    }

    /**
     * GET /v1/imports/{id} — status and counters.
     *
     * @return Result<mixed> `ImportJob`.
     */
    public function get(string $id): Result
    {
        return $this->http->request('GET', self::path($id));
    }

    /**
     * POST /v1/imports/{id}/emit — emit every valid row (answers 202). `402` when the credits are not enough.
     *
     * @return Result<mixed> `ImportJob`.
     */
    public function emit(string $id, ?bool $sendEmail = null): Result
    {
        return $this->http->request(
            'POST',
            self::path($id) . '/emit',
            json: $sendEmail !== null ? ['sendEmail' => $sendEmail] : null,
        );
    }

    /**
     * GET /v1/imports/{id}/rows — one page at a time (default 50, at most 200).
     *
     * @param array{status?: string, limit?: int, cursor?: string} $params `status`: `valid`,
     *        `invalid`, `queued`, `accepted` or `rejected`.
     *
     * @return Result<mixed> array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function listRows(string $id, array $params = []): Result
    {
        return $this->http->list(self::path($id) . '/rows', $params, self::plain());
    }

    /**
     * Walk every page of GET /v1/imports/{id}/rows.
     *
     * @param array{status?: string, limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterateRows(string $id, array $params = []): \Generator
    {
        return $this->http->iterate(self::path($id) . '/rows', $params, self::plain());
    }

    /**
     * PATCH /v1/imports/{id}/rows/{line} — correct one row and check it again (only while the import is `ready`).
     *
     * @param array<string, mixed> $item the corrected row, keyed like the spreadsheet columns.
     *
     * @return Result<mixed> `ImportRow`.
     */
    public function updateRow(string $id, int $line, array $item): Result
    {
        return $this->http->request(
            'PATCH',
            self::path($id) . '/rows/' . $line,
            json: ['item' => $item],
        );
    }

    /**
     * DELETE /v1/imports/{id} — discard an import that has not been emitted (204: null data).
     *
     * @return Result<mixed>
     */
    public function discard(string $id): Result
    {
        return $this->http->request('DELETE', self::path($id));
    }

    /**
     * GET /v1/imports/template — the spreadsheet template (CSV) as a DownloadedFile.
     *
     * @return Result<mixed>
     */
    public function downloadTemplate(): Result
    {
        return $this->http->requestRaw('GET', '/imports/template');
    }

    /**
     * GET /v1/imports/{id}/errors.csv — the rows that need attention (CSV) as a DownloadedFile.
     *
     * @return Result<mixed>
     */
    public function downloadErrors(string $id): Result
    {
        return $this->http->requestRaw('GET', self::path($id) . '/errors.csv');
    }

    private static function path(string $id): string
    {
        return '/imports/' . rawurlencode($id);
    }

    /** @return \Closure(mixed): mixed */
    private static function plain(): \Closure
    {
        return static fn (mixed $item): mixed => $item;
    }
}
