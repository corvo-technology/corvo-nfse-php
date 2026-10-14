<?php

declare(strict_types=1);

namespace CorvoNfse\Tests;

use CorvoNfse\CorvoApiError;
use CorvoNfse\Result;
use PHPUnit\Framework\TestCase;

final class CorvoApiErrorTest extends TestCase
{
    public function testCarriesAllSharedFields(): void
    {
        $details = [
            ['field' => 'customer.taxId', 'code' => 'invalid', 'message' => 'Invalid tax id'],
        ];

        $error = new CorvoApiError(
            status: 422,
            code: 'validation_error',
            message: 'Validation failed',
            details: $details,
            requestId: 'req_9',
            retryAfter: 30,
        );

        self::assertSame(422, $error->status);
        self::assertSame('validation_error', $error->code);
        self::assertSame('Validation failed', $error->getMessage());
        self::assertSame($details, $error->details);
        self::assertSame('req_9', $error->requestId);
        self::assertSame(30, $error->retryAfter);
    }

    public function testOptionalFieldsDefaultToEmptyOrNull(): void
    {
        $error = new CorvoApiError(500, 'internal_error', 'boom');

        self::assertSame([], $error->details);
        self::assertNull($error->requestId);
        self::assertNull($error->retryAfter);
    }

    public function testIsThrowableSoIterateCanRaiseIt(): void
    {
        $error = new CorvoApiError(401, 'unauthorized', 'nope');

        self::assertInstanceOf(\Throwable::class, $error);
    }

    public function testResultSuccessHasDataAndNoError(): void
    {
        $result = new Result(['id' => 'c_1'], null);

        self::assertTrue($result->isOk());
        self::assertFalse($result->isError());
        self::assertNull($result->error);
        self::assertSame(['id' => 'c_1'], $result->data);
    }

    public function testResultFailureHasErrorAndNoData(): void
    {
        $error = new CorvoApiError(404, 'not_found', 'Company not found');
        $result = new Result(null, $error);

        self::assertFalse($result->isOk());
        self::assertTrue($result->isError());
        self::assertNull($result->data);
        self::assertSame($error, $result->error);
    }

    public function testResultIsReadonly(): void
    {
        self::assertTrue((new \ReflectionClass(Result::class))->isReadOnly());
    }
}
