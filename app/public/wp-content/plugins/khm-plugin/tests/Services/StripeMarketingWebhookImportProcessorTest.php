<?php
/**
 * Tests for StripeMarketingWebhookImportProcessor retry/dead-letter behavior.
 *
 * @package KHM\Tests\Services
 */

namespace {
	if ( ! isset( $GLOBALS['khm_test_scheduled_events'] ) ) {
		$GLOBALS['khm_test_scheduled_events'] = [];
	}

	if ( ! function_exists( 'wp_next_scheduled' ) ) {
		function wp_next_scheduled( $hook, $args = [] ) {
			foreach ( $GLOBALS['khm_test_scheduled_events'] as $event ) {
				if ( $event['hook'] === $hook && $event['args'] === $args ) {
					return $event['timestamp'];
				}
			}
			return false;
		}
	}

	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		function wp_schedule_single_event( $timestamp, $hook, $args = [], $wp_error = false ) {
			$GLOBALS['khm_test_scheduled_events'][] = [
				'timestamp' => (int) $timestamp,
				'hook' => (string) $hook,
				'args' => (array) $args,
			];
			return true;
		}
	}
}

namespace KHM\Tests\Services {

use KHM\Services\StripeMarketingImportDeadLetterStore;
use KHM\Services\StripeMarketingImporter;
use KHM\Services\StripeMarketingWebhookImportProcessor;
use PHPUnit\Framework\TestCase;

class StripeMarketingWebhookImportProcessorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['khm_test_scheduled_events'] = [];
		$GLOBALS['khm_test_options'] = [
			'khm_stripe_marketing_import_max_attempts' => 3,
		];
	}

	public function test_retries_are_scheduled_on_import_exception(): void {
		$importer = $this->createMock( StripeMarketingImporter::class );
		$importer
			->expects( $this->once() )
			->method( 'importProductToLevel' )
			->willThrowException( new \RuntimeException( 'boom' ) );

		$deadLetters = $this->createMock( StripeMarketingImportDeadLetterStore::class );
		$deadLetters->expects( $this->never() )->method( 'insert' );

		$processor = new StripeMarketingWebhookImportProcessor( $importer, $deadLetters );
		$result = $processor->process( 'prod_retry_1', 12, 0 );

		$this->assertSame( 'retry_scheduled', $result['status'] );
		$this->assertCount( 1, $GLOBALS['khm_test_scheduled_events'] );
		$this->assertSame( [ 'prod_retry_1', 12, 1 ], $GLOBALS['khm_test_scheduled_events'][0]['args'] );
	}

	public function test_dead_letter_is_written_after_max_attempts(): void {
		$importer = $this->createMock( StripeMarketingImporter::class );
		$importer
			->expects( $this->once() )
			->method( 'importProductToLevel' )
			->willThrowException( new \RuntimeException( 'boom max' ) );

		$deadLetters = $this->createMock( StripeMarketingImportDeadLetterStore::class );
		$deadLetters
			->expects( $this->once() )
			->method( 'insert' )
			->with(
				$this->callback( function ( $payload ) {
					return isset( $payload['product_id'], $payload['attempts'], $payload['error_message'] )
						&& $payload['product_id'] === 'prod_retry_2'
						&& (int) $payload['attempts'] === 3;
				} )
			);

		$processor = new StripeMarketingWebhookImportProcessor( $importer, $deadLetters );
		$result = $processor->process( 'prod_retry_2', 88, 2 );

		$this->assertSame( 'dead_lettered', $result['status'] );
		$this->assertCount( 0, $GLOBALS['khm_test_scheduled_events'] );
	}

	public function test_locked_result_schedules_retry(): void {
		$importer = $this->createMock( StripeMarketingImporter::class );
		$importer
			->expects( $this->once() )
			->method( 'importProductToLevel' )
			->willReturn(
				[
					'level_id' => 44,
					'lines' => [],
					'dry_run' => false,
					'changed' => false,
					'skipped_reason' => 'locked',
					'content_hash' => '',
				]
			);

		$deadLetters = $this->createMock( StripeMarketingImportDeadLetterStore::class );
		$deadLetters->expects( $this->never() )->method( 'insert' );

		$processor = new StripeMarketingWebhookImportProcessor( $importer, $deadLetters );
		$result = $processor->process( 'prod_lock_1', 44, 0 );

		$this->assertSame( 'retry_scheduled', $result['status'] );
		$this->assertCount( 1, $GLOBALS['khm_test_scheduled_events'] );
		$this->assertSame( [ 'prod_lock_1', 44, 1 ], $GLOBALS['khm_test_scheduled_events'][0]['args'] );
	}
}
}
