<div class="wrap">
    <h1>SEO Suite</h1>

    <div class="kh-dashboard-grid">
        <div class="kh-card">
            <div class="kh-card-header">
                <span class="dashicons dashicons-chart-area"></span>
                <h3>Overall SEO Score</h3>
            </div>
            <div class="kh-card-body">
                <div class="kh-metric"><?php echo esc_html( $data['overall_score'] ?? 0 ); ?></div>
                <div class="kh-metric-label">Score</div>
            </div>
        </div>

        <div class="kh-card">
            <div class="kh-card-header">
                <span class="dashicons dashicons-search"></span>
                <h3>Keywords</h3>
            </div>
            <div class="kh-card-body">
                <div class="kh-metric"><?php echo esc_html( $data['total_keywords'] ?? 0 ); ?></div>
                <div class="kh-metric-label">Tracked</div>
            </div>
        </div>

        <div class="kh-card">
            <div class="kh-card-header">
                <span class="dashicons dashicons-trending-up"></span>
                <h3>Top 10 Rankings</h3>
            </div>
            <div class="kh-card-body">
                <div class="kh-metric"><?php echo esc_html( $data['top_10_rankings'] ?? 0 ); ?></div>
                <div class="kh-metric-label">Keywords</div>
            </div>
        </div>

        <div class="kh-card">
            <div class="kh-card-header">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3>Recent Audits</h3>
            </div>
            <div class="kh-card-body">
                <div class="kh-metric"><?php echo esc_html( $data['recent_audits'] ?? 0 ); ?></div>
                <div class="kh-metric-label">Completed</div>
            </div>
        </div>
    </div>

    <div class="kh-quick-actions">
        <h2>Quick Actions</h2>
        <div class="kh-action-buttons">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-seo-agent-audit' ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-search"></span> Run SEO Audit
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-seo' ) ); ?>" class="button">
                <span class="dashicons dashicons-admin-settings"></span> SEO Settings
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-seo-schema' ) ); ?>" class="button">
                <span class="dashicons dashicons-editor-code"></span> Schema Markup
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-seo-tools' ) ); ?>" class="button">
                <span class="dashicons dashicons-admin-tools"></span> SEO Tools
            </a>
        </div>
    </div>
</div>