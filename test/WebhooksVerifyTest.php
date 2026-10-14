<?php

declare(strict_types=1);

namespace CorvoNfse\Tests;

use CorvoNfse\Webhooks;
use PHPUnit\Framework\TestCase;

final class WebhooksVerifyTest extends TestCase
{
    private const BODY = '{"type":"nfse.invoice.authorized","data":{"id":"inv_1"}}';
    private const SECRET = 'whsec_test_123';
    private const SIGNATURE = 'sha256=8f9bd040d8f717b19618ab3395ddfa4b8c176b8dc863195227b88dd85426bbbc';

    public function testAcceptsTheKnownHmacSha256Vector(): void
    {
        self::assertTrue(Webhooks::verifySignature(self::BODY, self::SIGNATURE, self::SECRET));
    }

    public function testAcceptsSignatureWithSurroundingWhitespace(): void
    {
        self::assertTrue(Webhooks::verifySignature(self::BODY, ' ' . self::SIGNATURE . "\n", self::SECRET));
    }

    public function testRejectsTamperedBody(): void
    {
        $tampered = str_replace('inv_1', 'inv_2', self::BODY);

        self::assertFalse(Webhooks::verifySignature($tampered, self::SIGNATURE, self::SECRET));
    }

    public function testRejectsWrongSecret(): void
    {
        self::assertFalse(Webhooks::verifySignature(self::BODY, self::SIGNATURE, 'whsec_other'));
    }

    public function testRejectsMalformedHeadersAndSecrets(): void
    {
        $hex = '8f9bd040d8f717b19618ab3395ddfa4b8c176b8dc863195227b88dd85426bbbc';

        self::assertFalse(Webhooks::verifySignature(self::BODY, null, self::SECRET));
        self::assertFalse(Webhooks::verifySignature(self::BODY, $hex, self::SECRET));
        self::assertFalse(Webhooks::verifySignature(self::BODY, 'sha256=zzzz', self::SECRET));
        self::assertFalse(Webhooks::verifySignature(self::BODY, 'sha256=' . strtoupper($hex), self::SECRET));
        self::assertFalse(Webhooks::verifySignature(self::BODY, '', self::SECRET));
        self::assertFalse(Webhooks::verifySignature(self::BODY, self::SIGNATURE, ''));
    }

    public function testExportsNineWebhookEvents(): void
    {
        self::assertCount(9, Webhooks::WEBHOOK_EVENTS);
        self::assertContains('webhook.test', Webhooks::WEBHOOK_EVENTS);
        self::assertContains('nfse.invoice.authorized', Webhooks::WEBHOOK_EVENTS);
    }
}
