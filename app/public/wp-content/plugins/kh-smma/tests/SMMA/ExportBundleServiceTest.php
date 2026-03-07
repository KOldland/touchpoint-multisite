<?php

use KH_SMMA\Services\ExportBundleService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';

final class ExportBundleServiceTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp_dir = sys_get_temp_dir() . '/smma_export_' . uniqid();
		mkdir( $this->tmp_dir, 0775, true );
		$GLOBALS['kh_test_options'] = array();
	}

	public function test_create_bundle_generates_zip_and_manifest(): void {
		$service = new ExportBundleService( $this->tmp_dir );
		$schedule = array(
			'schedule_id' => 'sch_100',
			'variant_id' => 'var_100',
			'schedule_time' => '2026-04-01T10:00:00Z',
			'boost_options' => array(
				'budget_cents' => 12000,
				'channels' => array( 'linkedin' ),
			),
			'compliance_status' => 'OK',
			'approval_status' => 'approved',
		);
		$variant = array(
			'variant_id' => 'var_100',
			'linkedIn' => array(
				'text' => 'Card 05 export text.',
			),
		);

		$bundle = $service->create_bundle( $schedule, $variant );
		$this->assertSame( 'sch_100', $bundle['schedule_id'] );
		$this->assertFileExists( $bundle['file_path'] );
		$this->assertStringContainsString( 'schedule_export_sch_100.zip', $bundle['file_name'] );
		$this->assertGreaterThan( 0, (int) $bundle['bundle_size'] );
		$this->assertSame( 'sch_100', $bundle['manifest']['schedule_id'] );
		$this->assertSame( 'var_100', $bundle['manifest']['variant_id'] );
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
		$this->assertTrue( $this->contains_entry( $entries, 'manifest.json' ) );
		$this->assertTrue( $this->contains_entry( $entries, 'variant_text.txt' ) );
	}

	public function test_bundle_is_saved_for_schedule_lookup(): void {
		$service = new ExportBundleService( $this->tmp_dir );
		$schedule = array(
			'schedule_id' => 'sch_101',
			'variant_id' => 'var_101',
			'boost_options' => array( 'budget_cents' => 5000, 'channels' => array( 'linkedin' ) ),
			'compliance_status' => 'OK',
			'approval_status' => 'approved',
		);

		$service->create_bundle( $schedule, array( 'linkedIn' => array( 'text' => 'Hello export.' ) ) );
		$saved = $service->get_bundle( 'sch_101' );

		$this->assertSame( 'sch_101', $saved['schedule_id'] );
		$this->assertSame( 'var_101', $saved['variant_id'] );
		$this->assertSame( 'approved', $saved['manifest']['approval_status'] );
	}

	private function contains_entry( array $entries, string $needle ): bool {
		foreach ( $entries as $entry ) {
			if ( str_ends_with( $entry, $needle ) ) {
				return true;
			}
		}
		return false;
	}
}
