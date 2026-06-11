<div class="wrap kh-dashboard">
    <h1><?php esc_html_e( 'Editorial Studio Dashboard', 'kh-dashboards' ); ?></h1>
    <p class="kh-dashboard-subtitle"><?php esc_html_e( 'Content pipeline, authors, and AI tools at a glance.', 'kh-dashboards' ); ?></p>

    <div class="kh-cards">
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-admin-post"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['total_posts'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Published Posts', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-calendar"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['posts_this_month'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'This Month', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-edit"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['draft_posts'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Drafts', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-clock"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['scheduled_posts'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Scheduled', 'kh-dashboards' ); ?></span>
            </div>
        </div>
        <div class="kh-card">
            <span class="kh-card-icon dashicons dashicons-admin-users"></span>
            <div class="kh-card-body">
                <span class="kh-card-number"><?php echo esc_html( $data['total_authors'] ); ?></span>
                <span class="kh-card-label"><?php esc_html_e( 'Authors', 'kh-dashboards' ); ?></span>
            </div>
        </div>
    </div>

    <div class="kh-actions-panel">
        <h2><?php esc_html_e( 'Quick Actions', 'kh-dashboards' ); ?></h2>
        <div class="kh-actions-grid">
            <a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="kh-action-button kh-action-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <span><?php esc_html_e( 'New Post', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-post"></span>
                <span><?php esc_html_e( 'All Posts', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=multi_author' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-users"></span>
                <span><?php esc_html_e( 'Authors', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-planner' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-calendar-alt"></span>
                <span><?php esc_html_e( 'Planner', 'kh-dashboards' ); ?></span>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kh-editorial-settings' ) ); ?>" class="kh-action-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php esc_html_e( 'API Settings', 'kh-dashboards' ); ?></span>
            </a>
        </div>
    </div>
</div>