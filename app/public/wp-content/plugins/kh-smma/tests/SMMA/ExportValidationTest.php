<?php

use KH_SMMA\Services\ExportBundleService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';

final class ExportValidationTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp_dir = sys_get_temp_dir() . '/smma_export_validation_' . uniqid();
		mkdir( $this->tmp_dir, 0775, true );
		$GLOBALS['kh_test_options'] = array();
	}

	public function test_export_bundle_contains_required_files_and_manifest(): void {
		$fixture = json_decode(
			(string) file_get_contents( dirname( __DIR__ ) . '/fixtures/smma/export_manifest.json' ),
			true
		);
		$service = new ExportBundleService( $this->tmp_dir );

		$bundle = $service->create_bundle(
			array(
				'schedule_id' => (string) $fixture['schedule_id'],
				'variant_id' => (string) $fixture['variant_id'],
				'approval_status' => (string) $fixture['approval_status'],
				'compliance_status' => (string) $fixture['compliance_status'],
				'boost_options' => array(
					'budget_cents' => 12000,
					'channels' => array( 'linkedin' ),
				),
			),
			array(
				'variant_id' => (string) $fixture['variant_id'],
				'linkedIn' => array( 'text' => (string) $fixture['post_text'] ),
			)
		);

		$this->assertFileExists( $bundle['file_path'] );
		$this->assertSame( (string) $fixture['schedule_id'], $bundle['manifest']['schedule_id'] );
		$this->assertArrayHasKey( 'estimated_spend', $bundle['manifest'] );
		$this->assertArrayHasKey( 'estimated_ops', $bundle['manifest'] );

		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $bundle['file_path'] ) );
		$entries = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( is_array( $stat ) && isset( $stat['name'] ) ) {
				$entries[] = (string) $stat['name'];
			}
		}
		$zip->close();

		$this->assertTrue( $this->contains( $entries, 'manifest.json' ) );
		$this->assertTrue( $this->contains( $entries, 'variant_text.txt' ) );
	}

	private function contains( array $entries, string $needle ): bool {
		foreach ( $entries as $entry ) {
			if ( str_ends_with( $entry, $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
