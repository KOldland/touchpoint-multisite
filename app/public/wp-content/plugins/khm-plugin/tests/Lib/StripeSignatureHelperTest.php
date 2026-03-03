<?php

namespace KHM\Tests\Lib;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/helpers/stripe_signature.php';

class StripeSignatureHelperTest extends TestCase {
	public function test_builds_expected_signature_header(): void {
		$payload = '{"id":"evt_123","type":"checkout.session.completed"}';
		$secret = 'whsec_test_secret';
		$timestamp = 1710000000;

		$header = khm_test_build_stripe_signature_header($payload, $secret, $timestamp);

		$this->assertStringStartsWith('t=1710000000,v1=', $header);
		$this->assertSame(
			't=1710000000,v1=0255edfb26c7a3d127e2c881fab033db1265d58591b06fa677f34024312537c8',
			$header
		);
	}
}
