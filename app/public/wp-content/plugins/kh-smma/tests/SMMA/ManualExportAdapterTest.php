<?php

namespace KH_SMMA\Tests\SMMA;

use KH_SMMA\Adapters\ManualExportAdapter;
use KH_SMMA\Services\ExportBundleService;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/TestHelpers.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/PaidAdapterContract.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/AdapterIdempotencyStore.php';
require_once dirname( __DIR__, 2 ) . '/src/Services/ExportBundleService.php';
require_once dirname( __DIR__, 2 ) . '/src/Adapters/ManualExportAdapter.php';

final class ManualExportAdapterTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->tmp_dir = sys_get_temp_dir() . '/smma_adapter_export_' . uniqid();
		mkdir( $this->tmp_dir, 0775, true );
		$GLOBALS['kh_test_options'] = array();
	}

	public function test_create_schedule_export_bundle_returns_zip_bundle(): void {
		$service = new ExportBundleService( $this->tmp_dir );
		$adapter = new ManualExportAdapter( null, null, $service );

		$bundle = $adapter->create_schedule_export_bundle(
			array(
				'schedule_id' => 'sch_500',
				'variant_id' => 'var_500',
				'boost_options' => array(
					'budget_cents' => 25000,
					'channels' => array( 'linkedin' ),
				),
				'compliance_status' => 'OK',
				'approval_status' => 'approved',
			),
			array(
				'linkedIn' => array( 'text' => 'Adapter export content.' ),
			)
		);

		$this->assertSame( 'sch_500', $bundle['schedule_id'] );
		$this->assertFileExists( $bundle['file_path'] );
		$this->assertSame( 'approved', $bundle['manifest']['approval_status'] );
		$this->assertSame( 'OK', $bundle['manifest']['compliance_status'] );
	}
}
