<?php

declare(strict_types=1);

namespace AlphaPay\Tests;

use AlphaPay\Exceptions\AlphaPayWebhookSignatureException;
use AlphaPay\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function sign(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET);
    }

    public function testAcceptsACorrectlySignedFreshPayloadAndReturnsTheParsedEvent(): void
    {
        $body = json_encode(['event' => 'payment.succeeded', 'data' => ['id' => 'tx_123']]);
        $timestamp = time();

        $event = Webhook::verifySignature($body, $this->sign($body, $timestamp), $timestamp, self::SECRET);

        self::assertSame(['event' => 'payment.succeeded', 'data' => ['id' => 'tx_123']], $event);
    }

    public function testRejectsAPayloadSignedWithTheWrongSecret(): void
    {
        $body = json_encode(['event' => 'payment.succeeded', 'data' => []]);
        $timestamp = time();
        $wrongSignature = hash_hmac('sha256', "{$timestamp}.{$body}", 'wrong_secret');

        $this->expectException(AlphaPayWebhookSignatureException::class);
        Webhook::verifySignature($body, $wrongSignature, $timestamp, self::SECRET);
    }

    public function testRejectsAReplayedPayloadWhoseTimestampIsOutsideTheToleranceWindow(): void
    {
        $body = json_encode(['event' => 'payment.succeeded', 'data' => []]);
        $staleTimestamp = time() - 10_000;

        $this->expectException(AlphaPayWebhookSignatureException::class);
        $this->expectExceptionMessageMatches('/tolérance/');
        Webhook::verifySignature($body, $this->sign($body, $staleTimestamp), $staleTimestamp, self::SECRET);
    }

    public function testRejectsATamperedBodyEvenIfTheSignatureWasValidForTheOriginalBody(): void
    {
        $originalBody = json_encode(['event' => 'payment.succeeded', 'data' => ['amount' => '100']]);
        $timestamp = time();
        $signature = $this->sign($originalBody, $timestamp);
        $tamperedBody = json_encode(['event' => 'payment.succeeded', 'data' => ['amount' => '999999']]);

        $this->expectException(AlphaPayWebhookSignatureException::class);
        Webhook::verifySignature($tamperedBody, $signature, $timestamp, self::SECRET);
    }
}
