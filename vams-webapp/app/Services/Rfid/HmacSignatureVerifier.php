<?php

namespace App\Services\Rfid;

/**
 * Computes and verifies HMAC-SHA256 signatures for RFID device-service
 * requests. Kept as a small, dependency-free service (rather than inline
 * logic in the middleware) so it can be unit-tested and swapped/mocked
 * independently, per the Dependency Inversion guidance in context/RULES.md.
 */
class HmacSignatureVerifier
{
    /**
     * Compute the expected signature for a request.
     *
     * The signed payload binds the reader's api_key, the request timestamp,
     * the nonce, and the raw request body together, so a signature cannot be
     * replayed against a different body, reader, or time window.
     */
    public function sign(string $secret, string $apiKey, string $timestamp, string $nonce, string $rawBody): string
    {
        $payload = implode('.', [$apiKey, $timestamp, $nonce, $rawBody]);

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify a supplied signature against the expected one, in constant time.
     */
    public function verify(string $secret, string $apiKey, string $timestamp, string $nonce, string $rawBody, string $signature): bool
    {
        $expected = $this->sign($secret, $apiKey, $timestamp, $nonce, $rawBody);

        return hash_equals($expected, $signature);
    }
}
