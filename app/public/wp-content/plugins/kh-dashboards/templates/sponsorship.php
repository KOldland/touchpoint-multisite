<div class="wrap kh-dashboard">
    <h1><?php esc_html_e( 'Sponsorship & Promotion Dashboard', 'kh-dashboards' ); ?></h1>
    <p class="kh-dashboard-subtitle"><?php esc_html_e( 'Overview of sponsors, ads, events, and connected services.', 'kh-dashboards' ); ?></p>

    <div class="kh-cards">
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-groups"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['active_sponsors'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Active Sponsors', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-screenoptions"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['total_ads'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Total Ads', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-calendar-alt"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['active_events'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Events', 'kh-dashboards' ); ?></span>
            </div>
        </div>
    </div>

    <div class="kh-actions-panel">
        <h2><?php esc_html_e( 'Quick Actions', 'kh-dashboards' ); ?></h2>
        <div class="kh-actions-grid">
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=sponsor' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-groups"></span>
                <span><?php esc_html_e( 'Sponsors', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-ad-manager' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-screenoptions"></span>
                <span><?php esc_html_e( 'Ads', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-connect' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-networking"></span>
                <span><?php esc_html_e( 'Connect', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=event' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php esc_html_e( 'Events', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mailchimp' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-email-alt"></span>
                <span><?php esc_html_e( 'MailChimp', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=kh-ad-settings' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php esc_html_e( 'Settings', 'kh-dashboards' ); ?></span>
            </a>
        </div>
    </div>
</div>