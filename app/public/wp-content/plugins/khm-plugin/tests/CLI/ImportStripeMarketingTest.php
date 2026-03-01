<?php
/**
 * Tests for Stripe marketing import CLI command.
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
			public static array $errors = [];

			public static function line( $message ): void {
				self::$lines[] = (string) $message;
			}

			public static function success( $message ): void {
				self::$successes[] = (string) $message;
			}

			public static function error( $message ): void {
				self::$errors[] = (string) $message;
				throw new \RuntimeException( (string) $message );
			}

			public static function colorize( $message ) {
				return (string) $message;
			}
		}
	}
}

namespace KHM\Tests\CLI {
	use KHM\CLI\ImportStripeMarketingCommand;
	use KHM\Services\StripeMarketingImporter;
	use PHPUnit\Framework\TestCase;

	class ImportStripeMarketingTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			\WP_CLI::$lines = [];
			\WP_CLI::$successes = [];
			\WP_CLI::$errors = [];
		}

		public function test_dry_run_import_outputs_summary(): void {
			$importer = $this->createMock( StripeMarketingImporter::class );
			$importer
				->expects( $this->once() )
				->method( 'importProductToLevel' )
				->with( 'prod_123', 10, true, 'cli' )
				->willReturn(
					[
						'level_id' => 10,
						'lines' => [ 'Feature A', 'Feature B' ],
						'dry_run' => true,
						'changed' => false,
						'skipped_reason' => null,
						'content_hash' => 'abc',
					]
				);

			$command = new ImportStripeMarketingCommand( $importer );
			$command->__invoke( [ 'prod_123' ], [ 'level' => '10', 'dry-run' => true ] );

			$this->assertNotEmpty( \WP_CLI::$lines );
			$this->assertContains( 'Dry run completed.', \WP_CLI::$successes );
			$this->assertContains( 'Level: 10', \WP_CLI::$lines );
		}
	}
}
