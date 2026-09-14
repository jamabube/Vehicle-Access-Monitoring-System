<?php

namespace Tests\Unit;

use App\Services\Rfid\HmacSignatureVerifier;
use PHPUnit\Framework\TestCase;

class HmacSignatureVerifierTest extends TestCase
{
    private HmacSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new HmacSignatureVerifier;
    }

    public function test_sign_is_deterministic_for_the_same_inputs(): void
    {
        $signatureA = $this->verifier->sign('secret', 'key', '123', 'nonce', '{}');
        $signatureB = $this->verifier->sign('secret', 'key', '123', 'nonce', '{}');

        $this->assertSame($signatureA, $signatureB);
    }

    public function test_verify_returns_true_for_a_matching_signature(): void
    {
        $signature = $this->verifier->sign('secret', 'key', '123', 'nonce', '{"epc":"ABC"}');

        $this->assertTrue($this->verifier->verify('secret', 'key', '123', 'nonce', '{"epc":"ABC"}', $signature));
    }

    public function test_verify_returns_false_when_the_secret_differs(): void
    {
        $signature = $this->verifier->sign('secret', 'key', '123', 'nonce', '{}');

        $this->assertFalse($this->verifier->verify('different-secret', 'key', '123', 'nonce', '{}', $signature));
    }

    public function test_verify_returns_false_when_the_body_is_tampered_with(): void
    {
        $signature = $this->verifier->sign('secret', 'key', '123', 'nonce', '{"amount":1}');

        $this->assertFalse($this->verifier->verify('secret', 'key', '123', 'nonce', '{"amount":999}', $signature));
    }

    public function test_verify_returns_false_when_the_nonce_differs(): void
    {
        $signature = $this->verifier->sign('secret', 'key', '123', 'nonce-a', '{}');

        $this->assertFalse($this->verifier->verify('secret', 'key', '123', 'nonce-b', '{}', $signature));
    }
}
