<?php
/**
 * Reports Admin Page
 *
 * Displays analytics dashboards and reports.
 *
 * @package KHM\Admin
 */

namespace KHM\Admin;

use KHM\Services\ReportsService;

/**
 * Reports admin page controller.
 */
class ReportsPage {

	/**
	 * Reports service.
	 *
	 * @var ReportsService
	 */
	private ReportsService $reports;

	/**
	 * Constructor.
	 *
	 * @param ReportsService $reports Reports service.
	 */
	public function __construct( ReportsService $reports ) {
		$this->reports = $reports;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_khm_export_discounts_csv', array( $this, 'export_discounts_csv' ) );
	}

	/**
	 * Add reports menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'khm-dashboard',
			__( 'Reports', 'khm-membership' ),
			__( 'Reports', 'khm-membership' ),
			'manage_khm',
			'khm-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'membership_page_khm-reports' !== $hook ) {
			return;
		}

		// Enqueue Chart.js for visualizations.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_style(
			'khm-reports',
			plugins_url( 'css/admin.css', dirname( __DIR__, 2 ) . '/khm-plugin.php' ),
			array(),
			'1.0.0'
		);
	}

	/**
	 * Render reports page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET request for report viewing.
		$report = isset( $_GET['report'] ) ? sanitize_key( $_GET['report'] ) : 'dashboard';

		?>
		<div class="wrap khm-reports">
			<h1><?php esc_html_e( 'Reports & Analytics', 'khm-membership' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=khm-reports&report=dashboard" class="nav-tab <?php echo 'dashboard' === $report ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Dashboard', 'khm-membership' ); ?>
				</a>
				<a href="?page=khm-reports&report=revenue" class="nav-tab <?php echo 'revenue' === $report ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Revenue', 'khm-membership' ); ?>
				</a>
				<a href="?page=khm-reports&report=memberships" class="nav-tab <?php echo 'memberships' === $report ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Memberships', 'khm-membership' ); ?>
				</a>
				<a href="?page=khm-reports&report=mrr" class="nav-tab <?php echo 'mrr' === $report ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'MRR & Churn', 'khm-membership' ); ?>
				</a>
				<a href="?page=khm-reports&report=discounts" class="nav-tab <?php echo 'discounts' === $report ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Discounts', 'khm-membership' ); ?>
				</a>
			</nav>

			<div class="khm-reports-content">
				<?php
				match ( $report ) {
					'revenue'     => $this->render_revenue_report(),
					'memberships' => $this->render_memberships_report(),
					'mrr'         => $this->render_mrr_report(),
					'discounts'   => $this->render_discounts_report(),
					default       => $this->render_dashboard(),
				};
		?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render discounts report (top codes, totals, CSV export).
	 *
	 * @return void
	 */
	private function render_discounts_report(): void {
		$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'this_month'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$totals = $this->reports->get_discount_totals( $period );
		$top    = $this->reports->get_top_discount_codes( $period, 10 );

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=khm_export_discounts_csv&period=' . urlencode( $period ) ), 'khm_export_discounts_csv' );
		?>
		<div class="khm-discounts-report">
			<h2><?php esc_html_e( 'Discounts', 'khm-membership' ); ?></h2>
			<p>
				<label for="khm-period"><?php esc_html_e( 'Period:', 'khm-membership' ); ?></label>
				<select id="khm-period" onchange="location.href='?page=khm-reports&report=discounts&period='+this.value">
					<?php foreach ( [ 'today' => __( 'Today', 'khm-membership' ), 'this_month' => __( 'This Month', 'khm-membership' ), 'this_year' => __( 'This Year', 'khm-membership' ), 'all_time' => __( 'All Time', 'khm-membership' ) ] as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<a class="button button-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'khm-membership' ); ?></a>
			</p>

			<div class="khm-widget">
				<h3><?php esc_html_e( 'Totals', 'khm-membership' ); ?></h3>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Orders with Discounts', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $totals['orders_with_discount'] ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Total Discount Given', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( $this->format_currency( $totals['discounted'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="khm-widget">
				<h3><?php esc_html_e( 'Top Discount Codes', 'khm-membership' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Uses', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Discount Given', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Net Revenue', 'khm-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $top ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No discount usage found for this period.', 'khm-membership' ); ?></td></tr>
						<?php else : foreach ( $top as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['discount_code'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $row['uses'] ) ); ?></td>
								<td><?php echo esc_html( $this->format_currency( (float) $row['discounted'] ) ); ?></td>
								<td><?php echo esc_html( $this->format_currency( (float) $row['net_revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle CSV export for discounts report.
	 *
	 * @return void
	 */
	public function export_discounts_csv(): void {
		if ( ! current_user_can( 'manage_khm' ) || ! check_admin_referer( 'khm_export_discounts_csv' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'khm-membership' ) );
		}

		$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'this_month'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows   = $this->reports->get_top_discount_codes( $period, 1000 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=discounts_' . $period . '_' . gmdate( 'Ymd_His' ) . '.csv' );

		$fp = fopen( 'php://output', 'w' );
		fputcsv( $fp, array( 'discount_code', 'uses', 'discounted', 'net_revenue' ) );
		foreach ( $rows as $r ) {
			fputcsv( $fp, array( $r['discount_code'], (int) $r['uses'], (float) $r['discounted'], (float) $r['net_revenue'] ) );
		}
		fclose( $fp );
		exit;
	}

	/**
	 * Render dashboard overview.
	 *
	 * @return void
	 */
	private function render_dashboard(): void {
		?>
		<div class="khm-dashboard-widgets">
			<!-- Sales Widget -->
			<div class="khm-widget">
				<h2><?php esc_html_e( 'Sales & Revenue', 'khm-membership' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Period', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Sales', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Revenue', 'khm-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Today', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_sales( 'today' ) ) ); ?></td>
							<td><?php echo esc_html( $this->format_currency( $this->reports->get_revenue( 'today' ) ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'This Month', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_sales( 'this_month' ) ) ); ?></td>
							<td><?php echo esc_html( $this->format_currency( $this->reports->get_revenue( 'this_month' ) ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'This Year', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_sales( 'this_year' ) ) ); ?></td>
							<td><?php echo esc_html( $this->format_currency( $this->reports->get_revenue( 'this_year' ) ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'All Time', 'khm-membership' ); ?></strong></td>
							<td><strong><?php echo esc_html( number_format_i18n( $this->reports->get_sales( 'all_time' ) ) ); ?></strong></td>
							<td><strong><?php echo esc_html( $this->format_currency( $this->reports->get_revenue( 'all_time' ) ) ); ?></strong></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Memberships Widget -->
			<div class="khm-widget">
				<h2><?php esc_html_e( 'Membership Stats', 'khm-membership' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Period', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Signups', 'khm-membership' ); ?></th>
							<th><?php esc_html_e( 'Cancellations', 'khm-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Today', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_signups( 'today' ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_cancellations( 'today' ) ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'This Month', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_signups( 'this_month' ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_cancellations( 'this_month' ) ) ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'This Year', 'khm-membership' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_signups( 'this_year' ) ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $this->reports->get_cancellations( 'this_year' ) ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'All Time', 'khm-membership' ); ?></strong></td>
							<td><strong><?php echo esc_html( number_format_i18n( $this->reports->get_signups( 'all_time' ) ) ); ?></strong></td>
							<td><strong><?php echo esc_html( number_format_i18n( $this->reports->get_cancellations( 'all_time' ) ) ); ?></strong></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- MRR Widget -->
			<div class="khm-widget">
				<h2><?php esc_html_e( 'Key Metrics', 'khm-membership' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Monthly Recurring Revenue', 'khm-membership' ); ?></strong></td>
							<td class="khm-metric-value"><?php echo esc_html( $this->format_currency( $this->reports->calculate_mrr() ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Active Members', 'khm-membership' ); ?></strong></td>
							<td class="khm-metric-value"><?php echo esc_html( number_format_i18n( $this->reports->get_active_members_count() ) ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Churn Rate (This Month)', 'khm-membership' ); ?></strong></td>
							<td class="khm-metric-value"><?php echo esc_html( number_format_i18n( $this->reports->get_churn_rate( 'this_month' ), 2 ) ); ?>%</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<style>
			.khm-dashboard-widgets {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			.khm-widget {
				background: #fff;
				border: 1px solid #ccd0d4;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				padding: 20px;
			}
			.khm-widget h2 {
				margin-top: 0;
				margin-bottom: 15px;
				font-size: 18px;
				border-bottom: 1px solid #eee;
				padding-bottom: 10px;
			}
			.khm-widget table {
				margin: 0;
			}
			.khm-metric-value {
				text-align: right;
				font-size: 18px;
				color: #2271b1;
				font-weight: 600;
			}
		</style>
		<?php
	}

	/**
	 * Render revenue report.
	 *
	 * @return void
	 */
	private function render_revenue_report(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET request for report filtering.
		// Get date range from request or default to this month.
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' );
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-d' );
		$group_by   = isset( $_GET['group_by'] ) ? sanitize_key( $_GET['group_by'] ) : 'day';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$data = $this->reports->get_revenue_by_date( $start_date, $end_date, $group_by );

		?>
		<div class="khm-report-filters">
			<form method="get">
				<input type="hidden" name="page" value="khm-reports">
				<input type="hidden" name="report" value="revenue">
				
				<label><?php esc_html_e( 'Start Date:', 'khm-membership' ); ?></label>
				<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
				
				<label><?php esc_html_e( 'End Date:', 'khm-membership' ); ?></label>
				<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
				
				<label><?php esc_html_e( 'Group By:', 'khm-membership' ); ?></label>
				<select name="group_by">
					<option value="day" <?php selected( $group_by, 'day' ); ?>><?php esc_html_e( 'Day', 'khm-membership' ); ?></option>
					<option value="month" <?php selected( $group_by, 'month' ); ?>><?php esc_html_e( 'Month', 'khm-membership' ); ?></option>
					<option value="year" <?php selected( $group_by, 'year' ); ?>><?php esc_html_e( 'Year', 'khm-membership' ); ?></option>
				</select>
				
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'khm-membership' ); ?></button>
			</form>
		</div>

		<div class="khm-chart-container">
			<canvas id="revenueChart" width="400" height="150"></canvas>
		</div>

		<script>
		(function() {
			const data = <?php echo wp_json_encode( $data ); ?>;
			const labels = data.map(item => item.date);
			const revenue = data.map(item => parseFloat(item.revenue));
			const sales = data.map(item => parseInt(item.sales));

			const ctx = document.getElementById('revenueChart');
			new Chart(ctx, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: '<?php echo esc_js( __( 'Revenue', 'khm-membership' ) ); ?>',
						data: revenue,
						borderColor: 'rgb(34, 113, 177)',
						backgroundColor: 'rgba(34, 113, 177, 0.1)',
						yAxisID: 'y'
					}, {
						label: '<?php echo esc_js( __( 'Sales', 'khm-membership' ) ); ?>',
						data: sales,
						borderColor: 'rgb(75, 192, 192)',
						backgroundColor: 'rgba(75, 192, 192, 0.1)',
						yAxisID: 'y1'
					}]
				},
				options: {
					responsive: true,
					interaction: {
						mode: 'index',
						intersect: false,
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: '<?php echo esc_js( __( 'Revenue', 'khm-membership' ) ); ?>'
							}
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							title: {
								display: true,
								text: '<?php echo esc_js( __( 'Sales', 'khm-membership' ) ); ?>'
							},
							grid: {
								drawOnChartArea: false,
							}
						}
					}
				}
			});
		})();
		</script>

		<style>
			.khm-report-filters {
				background: #fff;
				padding: 20px;
				margin: 20px 0;
				border: 1px solid #ccd0d4;
			}
			.khm-report-filters form {
				display: flex;
				gap: 15px;
				align-items: center;
				flex-wrap: wrap;
			}
			.khm-chart-container {
				background: #fff;
				padding: 30px;
				margin: 20px 0;
				border: 1px solid #ccd0d4;
			}
		</style>
		<?php
	}

	/**
	 * Render memberships report.
	 *
	 * @return void
	 */
	private function render_memberships_report(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET request for report filtering.
		// Get date range from request or default to this month.
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' );
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-d' );
		$group_by   = isset( $_GET['group_by'] ) ? sanitize_key( $_GET['group_by'] ) : 'day';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Get memberships data grouped by date.
		$data = $this->get_memberships_by_date( $start_date, $end_date, $group_by );

		?>
		<div class="khm-report-filters">
			<form method="get">
				<input type="hidden" name="page" value="khm-reports">
				<input type="hidden" name="report" value="memberships">
				
				<label><?php esc_html_e( 'Start Date:', 'khm-membership' ); ?></label>
				<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
				
				<label><?php esc_html_e( 'End Date:', 'khm-membership' ); ?></label>
				<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
				
				<label><?php esc_html_e( 'Group By:', 'khm-membership' ); ?></label>
				<select name="group_by">
					<option value="day" <?php selected( $group_by, 'day' ); ?>><?php esc_html_e( 'Day', 'khm-membership' ); ?></option>
					<option value="month" <?php selected( $group_by, 'month' ); ?>><?php esc_html_e( 'Month', 'khm-membership' ); ?></option>
					<option value="year" <?php selected( $group_by, 'year' ); ?>><?php esc_html_e( 'Year', 'khm-membership' ); ?></option>
				</select>
				
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'khm-membership' ); ?></button>
			</form>
		</div>

		<div class="khm-chart-container">
			<canvas id="memberships-chart"></canvas>
		</div>

		<script>
		(function() {
			const ctx = document.getElementById('memberships-chart');
			const data = <?php echo wp_json_encode( $data ); ?>;
			
			new Chart(ctx, {
				type: 'line',
				data: {
					labels: data.map(d => d.date),
					datasets: [
						{
							label: '<?php echo esc_js( __( 'Signups', 'khm-membership' ) ); ?>',
							data: data.map(d => parseInt(d.signups)),
							borderColor: 'rgba(70, 180, 80, 0.8)',
							backgroundColor: 'rgba(70, 180, 80, 0.1)',
							tension: 0.4,
							fill: true,
							yAxisID: 'y'
						},
						{
							label: '<?php echo esc_js( __( 'Cancellations', 'khm-membership' ) ); ?>',
							data: data.map(d => parseInt(d.cancellations)),
							borderColor: 'rgba(220, 50, 50, 0.8)',
							backgroundColor: 'rgba(220, 50, 50, 0.1)',
							tension: 0.4,
							fill: true,
							yAxisID: 'y'
						},
						{
							label: '<?php echo esc_js( __( 'Net Growth', 'khm-membership' ) ); ?>',
							data: data.map(d => parseInt(d.signups) - parseInt(d.cancellations)),
							borderColor: 'rgba(34, 113, 177, 0.8)',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							tension: 0.4,
							fill: true,
							yAxisID: 'y',
							borderDash: [5, 5]
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: true,
					aspectRatio: 2.5,
					interaction: {
						mode: 'index',
						intersect: false,
					},
					plugins: {
						legend: {
							position: 'top',
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									let label = context.dataset.label || '';
									if (label) {
										label += ': ';
									}
									label += context.parsed.y + ' members';
									return label;
								}
							}
						}
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: '<?php echo esc_js( __( 'Members', 'khm-membership' ) ); ?>'
							},
							ticks: {
								stepSize: 1
							}
						}
					}
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render MRR report.
	 *
	 * @return void
	 */
	private function render_mrr_report(): void {
		$mrr        = $this->reports->calculate_mrr();
		$churn_rate = $this->reports->get_churn_rate( 'this_month' );
		$active     = $this->reports->get_active_members_count();

		?>
		<div class="khm-mrr-dashboard">
			<div class="khm-stat-box">
				<h3><?php esc_html_e( 'Monthly Recurring Revenue', 'khm-membership' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( $this->format_currency( $mrr ) ); ?></div>
				<p class="stat-desc"><?php esc_html_e( 'Current MRR from all active subscriptions', 'khm-membership' ); ?></p>
			</div>

			<div class="khm-stat-box">
				<h3><?php esc_html_e( 'Churn Rate', 'khm-membership' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( number_format_i18n( $churn_rate, 2 ) ); ?>%</div>
				<p class="stat-desc"><?php esc_html_e( 'Percentage of members cancelled this month', 'khm-membership' ); ?></p>
			</div>

			<div class="khm-stat-box">
				<h3><?php esc_html_e( 'Active Members', 'khm-membership' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( number_format_i18n( $active ) ); ?></div>
				<p class="stat-desc"><?php esc_html_e( 'Total active paying members', 'khm-membership' ); ?></p>
			</div>

			<div class="khm-stat-box">
				<h3><?php esc_html_e( 'Average Revenue Per User', 'khm-membership' ); ?></h3>
				<div class="stat-value"><?php echo esc_html( $this->format_currency( $active > 0 ? $mrr / $active : 0 ) ); ?></div>
				<p class="stat-desc"><?php esc_html_e( 'Average monthly revenue per active member', 'khm-membership' ); ?></p>
			</div>
		</div>

		<style>
			.khm-mrr-dashboard {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			.khm-stat-box {
				background: #fff;
				border: 1px solid #ccd0d4;
				padding: 30px;
				text-align: center;
			}
			.khm-stat-box h3 {
				margin: 0 0 15px 0;
				font-size: 14px;
				font-weight: 600;
				text-transform: uppercase;
				color: #666;
			}
			.khm-stat-box .stat-value {
				font-size: 36px;
				font-weight: 700;
				color: #2271b1;
				margin-bottom: 10px;
			}
			.khm-stat-box .stat-desc {
				font-size: 13px;
				color: #666;
				margin: 0;
			}
		</style>
		<?php
	}

	/**
	 * Get memberships data grouped by date.
	 *
	 * @param string $start_date Start date (Y-m-d format).
	 * @param string $end_date End date (Y-m-d format).
	 * @param string $group_by Grouping interval (day, month, year).
	 * @return array Array of objects with date, signups, and cancellations.
	 */
	private function get_memberships_by_date( string $start_date, string $end_date, string $group_by ): array {
		global $wpdb;

		// Determine date format based on grouping.
		$date_format = match ( $group_by ) {
			'year'  => '%Y',
			'month' => '%Y-%m',
			default => '%Y-%m-%d',
		};

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Safe: Table names use $wpdb->prefix, date format validated via match, user inputs prepared.

		// Get signups grouped by date.
		$signups = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(start_date, %s) as date, COUNT(*) as signups
				FROM {$wpdb->prefix}khm_memberships
				WHERE DATE(start_date) BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(start_date, %s)
				ORDER BY date ASC",
				$date_format,
				$start_date,
				$end_date,
				$date_format
			)
		);

		// Get cancellations grouped by date.
		$cancellations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(end_date, %s) as date, COUNT(*) as cancellations
				FROM {$wpdb->prefix}khm_memberships
				WHERE status IN ('cancelled', 'expired')
				  AND DATE(end_date) BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(end_date, %s)
				ORDER BY date ASC",
				$date_format,
				$start_date,
				$end_date,
				$date_format
			)
		);

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Merge signups and cancellations by date.
		$data = array();

		// Create a map of dates from both datasets.
		$dates = array();
		foreach ( $signups as $row ) {
			$dates[ $row->date ] = true;
		}
		foreach ( $cancellations as $row ) {
			$dates[ $row->date ] = true;
		}

		// Sort dates.
		$dates = array_keys( $dates );
		sort( $dates );

		// Build combined dataset.
		$signup_map = array();
		foreach ( $signups as $row ) {
			$signup_map[ $row->date ] = $row->signups;
		}

		$cancellation_map = array();
		foreach ( $cancellations as $row ) {
			$cancellation_map[ $row->date ] = $row->cancellations;
		}

		foreach ( $dates as $date ) {
			$data[] = (object) array(
				'date'          => $date,
				'signups'       => $signup_map[ $date ] ?? 0,
				'cancellations' => $cancellation_map[ $date ] ?? 0,
			);
		}

		return $data;
	}

	/**
	 * Format currency for display.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted currency string.
	 */
	private function format_currency( float $amount ): string {
		$currency = get_option( 'khm_currency', 'USD' );
		$symbol   = '$'; // Default.

		switch ( $currency ) {
			case 'EUR':
				$symbol = '€';
				break;
			case 'GBP':
				$symbol = '£';
				break;
			case 'JPY':
				$symbol = '¥';
				break;
		}

		return $symbol . number_format_i18n( $amount, 2 );
	}
}
