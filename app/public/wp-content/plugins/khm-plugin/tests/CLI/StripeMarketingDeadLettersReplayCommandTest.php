<?php
/**
 * Tests for StripeMarketingDeadLettersReplayCommand.
 *
 * @package KHM\Tests\CLI
 */

namespace WP_CLI\Utils {
	if ( ! function_exists( __NAMESPACE__ . '\\get_flag_value' ) ) {
		function get_flag_value( $assoc_args, $flag, $default = false ) {
			return $assoc_args[ $flag ] ?? $default;
		}
	}
}

namespace {
	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static array $lines = [];
			public static array $successes = [];
			public static array $warnings = [];
			public static array $errors = [];

			public static function line( $message ): void { self::$lines[] = (string) $message; }
			public static function success( $message ): void { self::$successes[] = (string) $message; }
			public static function warning( $message ): void { self::$warnings[] = (string) $message; }
			public static function error( $message ): void {
				self::$errors[] = (string) $message;
				throw new \RuntimeException( (string) $message );
			}
		}
	}
}

namespace KHM\Tests\CLI {

use KHM\CLI\StripeMarketingDeadLettersReplayCommand;
use KHM\Services\StripeMarketingImportDeadLetterStore;
use KHM\Services\StripeMarketingImporter;
use PHPUnit\Framework\TestCase;

class StripeMarketingDeadLettersReplayCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::$lines = [];
		\WP_CLI::$successes = [];
		\WP_CLI::$warnings = [];
		\WP_CLI::$errors = [];
	}

	public function test_replay_by_id_marks_dead_letter_resolved(): void {
		$importer = $this->createMock( StripeMarketingImporter::class );
		$importer
			->expects( $this->once() )
			->method( 'importProductToLevel' )
			->with( 'prod_dead_1', 22, false, 'replay' )
			->willReturn(
				[
					'level_id' => 22,
					'lines' => [ 'A' ],
					'dry_run' => false,
					'changed' => true,
					'skipped_reason' => null,
					'content_hash' => 'x',
				]
			);

		$store = $this->createMock( StripeMarketingImportDeadLetterStore::class );
		$store
			->expects( $this->once() )
			->method( 'getById' )
			->with( 7 )
			->willReturn(
				[
					'id' => 7,
					'product_id' => 'prod_dead_1',
					'level_id' => 22,
				]
			);
		$store
			->expects( $this->once() )
			->method( 'markResolved' )
			->with( 7 )
			->willReturn( true );

		$command = new StripeMarketingDeadLettersReplayCommand( $importer, $store );
		$command->__invoke( [], [ 'id' => 7 ] );

		$this->assertContains( 'Dead-letter replay completed.', \WP_CLI::$successes );
	}
}
}
