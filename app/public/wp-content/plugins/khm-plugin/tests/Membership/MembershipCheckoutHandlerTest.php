<?php

namespace KHM\Tests\Membership;

use KHM\Frontend\MembershipCheckoutHandler;
use PHPUnit\Framework\TestCase;

class MembershipCheckoutHandlerTest extends TestCase {
    public function test_sanitize_profile_payload_persists_explicit_marketing_opt_out(): void {
        $handler = new MembershipCheckoutHandler();
        $method = new \ReflectionMethod( MembershipCheckoutHandler::class, 'sanitize_profile_payload' );
        $method->setAccessible( true );

        $profile = [
            'first_name' => 'Taylor',
            'last_name' => 'Guest',
            'marketing_opt_in' => false,
        ];

        $sanitized = $method->invoke( $handler, $profile );

        $this->assertSame( 0, $sanitized['marketing_opt_in'] );
        $this->assertSame( 'Taylor', $sanitized['first_name'] );
        $this->assertSame( 'Guest', $sanitized['last_name'] );
    }

    public function test_sanitize_profile_payload_persists_explicit_marketing_opt_in(): void {
        $handler = new MembershipCheckoutHandler();
        $method = new \ReflectionMethod( MembershipCheckoutHandler::class, 'sanitize_profile_payload' );
        $method->setAccessible( true );

        $profile = [
            'marketing_opt_in' => true,
        ];

        $sanitized = $method->invoke( $handler, $profile );

        $this->assertSame( 1, $sanitized['marketing_opt_in'] );
    }
}
