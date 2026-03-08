<?php

namespace KHM\Tests\Lib;

use PHPUnit\Framework\TestCase;

class StripeEnvTest extends TestCase {
    protected function tearDown(): void {
        putenv('KH_STRIPE_SECRET_KEY');
        unset($_ENV['KH_STRIPE_SECRET_KEY']);
        parent::tearDown();
    }

    public function test_reads_secret_from_getenv(): void {
        putenv('KH_STRIPE_SECRET_KEY=sk_test_env_secret');

        $value = \khm_get_stripe_secret('KH_STRIPE_SECRET_KEY');
        $this->assertSame('sk_test_env_secret', $value);
    }

    public function test_returns_null_when_secret_missing(): void {
        putenv('KH_STRIPE_SECRET_KEY');
        unset($_ENV['KH_STRIPE_SECRET_KEY']);

        $value = \khm_get_stripe_secret('KH_STRIPE_SECRET_KEY');
        $this->assertNull($value);
    }
}
