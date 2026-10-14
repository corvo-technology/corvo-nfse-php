<?php

declare(strict_types=1);

namespace CorvoNfse\Resources;

use CorvoNfse\Result;
use CorvoNfse\Transport;

/**
 * Credits: balance, purchasable plans and the transaction history.
 */
final class Credits
{
    public function __construct(private readonly Transport $http)
    {
    }

    /**
     * GET /v1/credits — current balance.
     */
    public function balance(): Result
    {
        return $this->http->request('GET', '/credits');
    }

    /**
     * GET /v1/credits/plans — cursor-paginated, items unwrapped from `props`.
     *
     * @return Result array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function plans(?int $limit = null, ?string $cursor = null): Result
    {
        return $this->http->list('/credits/plans', ['limit' => $limit, 'cursor' => $cursor], self::mapProps());
    }

    /**
     * GET /v1/credits/history — cursor-paginated, items unwrapped from `props`.
     *
     * @return Result array{data: list<mixed>, pagination: array<string, mixed>}
     */
    public function history(?int $limit = null, ?string $cursor = null): Result
    {
        return $this->http->list('/credits/history', ['limit' => $limit, 'cursor' => $cursor], self::mapProps());
    }

    /**
     * Walk every page of GET /v1/credits/history.
     *
     * @param array{limit?: int, cursor?: string} $params
     *
     * @throws \CorvoNfse\CorvoApiError on a page failure.
     *
     * @return \Generator<int, mixed>
     */
    public function iterateHistory(array $params = []): \Generator
    {
        return $this->http->iterate('/credits/history', $params, self::mapProps());
    }

    /**
     * List items arrive as `{props: {...}}`; unwrap to the entity itself.
     */
    private static function mapProps(): \Closure
    {
        return static function (mixed $item): mixed {
            if (is_array($item) && array_key_exists('props', $item)) {
                return $item['props'];
            }

            return $item;
        };
    }
}
