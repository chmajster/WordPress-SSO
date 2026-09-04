<?php

declare(strict_types=1);

use Chmajster\PlanWordPressSSO\Protocol;
use PHPUnit\Framework\TestCase;

final class ProtocolTest extends TestCase
{
    public function testBase64UrlEncodingRemovesUnsafeCharactersAndPadding(): void
    {
        $encoded = Protocol::base64UrlEncode("\xfb\xff\xef");

        self::assertSame('-__v', $encoded);
        self::assertStringNotContainsString('=', $encoded);
        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
    }

    public function testSecretValidation(): void
    {
        self::assertTrue(Protocol::isValidSecret(str_repeat('a', 64)));
        self::assertTrue(Protocol::isValidSecret(str_repeat('A', 128)));
        self::assertFalse(Protocol::isValidSecret(str_repeat('a', 63)));
        self::assertFalse(Protocol::isValidSecret(str_repeat('g', 64)));
    }

    public function testEmailNormalization(): void
    {
        self::assertSame('user@example.org', Protocol::normalizeEmail(' User@Example.ORG '));
        self::assertSame('', Protocol::normalizeEmail('not-an-email'));
    }

    public function testEmailHistoryIsNewestFirstUniqueAndExcludesCurrentEmail(): void
    {
        $history = Protocol::normalizeEmailHistory(
            [
                ' Old1@Example.org ',
                'current@example.org',
                'old1@example.org',
                'bad',
                'old2@example.org',
                'old3@example.org',
                'old4@example.org',
                'old5@example.org',
                'old6@example.org',
            ],
            'current@example.org'
        );

        self::assertSame(
            [
                'old1@example.org',
                'old2@example.org',
                'old3@example.org',
                'old4@example.org',
                'old5@example.org',
            ],
            $history
        );
    }

    public function testHmacUsesSecretAsTextualKey(): void
    {
        $payload = 'eyJ0ZXN0Ijp0cnVlfQ';
        $secret = str_repeat('A1', 32);

        self::assertSame(
            hash_hmac('sha256', $payload, $secret),
            Protocol::sign($payload, $secret)
        );
    }

    public function testBuildPayloadPreservesContract(): void
    {
        $payload = Protocol::buildPayload(
            'Owner@Example.org',
            'User@Example.org',
            ['Old@Example.org', 'user@example.org'],
            'volunteer',
            1780000000,
            'abcdefghijklmnop'
        );

        self::assertSame(
            [
                'owner' => 'owner@example.org',
                'email' => 'user@example.org',
                'previous_emails' => ['old@example.org'],
                'role' => 'volunteer',
                'ts' => 1780000000,
                'nonce' => 'abcdefghijklmnop',
            ],
            $payload
        );
    }

    public function testNonceMatchesRequiredFormat(): void
    {
        $nonce = Protocol::generateNonce();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16,128}$/', $nonce);
    }
}
