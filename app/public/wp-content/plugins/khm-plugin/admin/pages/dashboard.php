<?php

use KHM\Admin\DashboardStatsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$service = new DashboardStatsService();
$stats   = $service->get_stats();

function khm_admin_format_money( float $amount ): string {
	return '$' . number_format_i18n( $amount, 2 );
}
?>
<div class="wrap khm-dashboard">
	<h1><?php esc_html_e( 'Membership Dashboard', 'khm-membership' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Quick snapshot of membership health and billing performance.', 'khm-membership' ); ?></p>

	<div class="khm-dashboard-grid">
		<div class="khm-dashboard-card">
			<h2><?php esc_html_e( 'Active Members', 'khm-membership' ); ?></h2>
			<p class="khm-dashboard-metric"><?php echo esc_html( number_format_i18n( $stats['active_members'] ) ); ?></p>
			<p class="khm-dashboard-subtext"><?php esc_html_e( 'Members with an active subscription.', 'khm-membership' ); ?></p>
		</div>
		<div class="khm-dashboard-card">
			<h2><?php esc_html_e( 'New Members (7 days)', 'khm-membership' ); ?></h2>
			<p class="khm-dashboard-metric"><?php echo esc_html( number_format_i18n( $stats['new_members_7'] ) ); ?></p>
			<p class="khm-dashboard-subtext"><?php esc_html_e( 'New subscriptions started in the last week.', 'khm-membership' ); ?></p>
		</div>
		<div class="khm-dashboard-card">
			<h2><?php esc_html_e( 'Revenue (30 days)', 'khm-membership' ); ?></h2>
			<p class="khm-dashboard-metric"><?php echo esc_html( khm_admin_format_money( (float) $stats['revenue_30'] ) ); ?></p>
			<p class="khm-dashboard-subtext"><?php esc_html_e( 'Completed order totals for the last 30 days.', 'khm-membership' ); ?></p>
		</div>
		<div class="khm-dashboard-card">
			<h2><?php esc_html_e( 'Pending Orders', 'khm-membership' ); ?></h2>
			<p class="khm-dashboard-metric"><?php echo esc_html( number_format_i18n( $stats['pending_orders'] ) ); ?></p>
			<p class="khm-dashboard-subtext"><?php esc_html_e( 'Orders awaiting payment completion.', 'khm-membership' ); ?></p>
		</div>
		<div class="khm-dashboard-card">
			<h2><?php esc_html_e( 'Failed Payments (7 days)', 'khm-membership' ); ?></h2>
			<p class="khm-dashboard-metric"><?php echo esc_html( number_format_i18n( $stats['failed_week'] ) ); ?></p>
			<p class="khm-dashboard-subtext"><?php esc_html_e( 'Monitor follow-ups for billing failures.', 'khm-membership' ); ?></p>
		</div>
	</div>

	<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-orders' ) ); ?>"><?php esc_html_e( 'View Orders', 'khm-membership' ); ?></a>
	<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=khm-members' ) ); ?>"><?php esc_html_e( 'View Members', 'khm-membership' ); ?></a></p>
</div>

<style>
.khm-dashboard-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 16px;
	margin: 24px 0;
}
.khm-dashboard-card {
	background: #fff;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	padding: 20px;
	box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}
.khm-dashboard-card h2 {
	margin: 0 0 12px;
	font-size: 16px;
}
.khm-dashboard-metric {
	margin: 0;
	font-size: 28px;
	font-weight: 600;
	color: #1d2327;
}
.khm-dashboard-subtext {
	margin: 8px 0 0;
	color: #646970;
}
.khm-member-notes {
	margin-top: 24px;
}
.khm-notes-list {
	list-style: none;
	margin: 0 0 16px;
	padding: 0;
}
.khm-note-item {
	border: 1px solid #dcdcde;
	padding: 12px;
	border-radius: 4px;
	margin-bottom: 12px;
	background: #fff;
}
.khm-note-meta {
	font-size: 13px;
	color: #646970;
	margin-bottom: 8px;
}
.khm-note-actions {
	margin-top: 8px;
}
.khm-note-actions .button-link.delete {
	color: #d63638;
}
</style>
