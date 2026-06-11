<div class="wrap kh-dashboard">
    <h1><?php esc_html_e( 'Commerce Dashboard', 'kh-dashboards' ); ?></h1>
    <p class="kh-dashboard-subtitle"><?php esc_html_e( 'Membership, orders, and revenue at a glance.', 'kh-dashboards' ); ?></p>

    <div class="kh-cards">
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-admin-users"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['active_members'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Active Members', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-cart"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['total_orders'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Orders', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-tag"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['total_levels'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Levels', 'kh-dashboards' ); ?></span>
            </div>
        </div>
    </div>

    <div class="kh-actions-panel">
        <h2><?php esc_html_e( 'Quick Actions', 'kh-dashboards' ); ?></h2>
        <div class="kh-actions-grid">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=members' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-users"></span>
                <span><?php esc_html_e( 'Members', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=orders' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-cart"></span>
                <span><?php esc_html_e( 'Orders', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=levels' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-tag"></span>
                <span><?php esc_html_e( 'Levels', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=membership-settings' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php esc_html_e( 'Settings', 'kh-dashboards' ); ?></span>
            </a>
        </div>
    </div>
</div>