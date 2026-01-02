<?php
/**
 * Extensions Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('KHM Membership Extensions', 'khm-membership'); ?></h1>

    <p class="about-description">
        <?php esc_html_e('Extend the functionality of KHM Membership with these powerful add-ons.', 'khm-membership'); ?>
    </p>

    <div class="khm-extensions-grid">
        <!-- Extension Card Example -->
        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-email"></span>
            </div>
            <h3><?php esc_html_e('Email Marketing Integration', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Automatically sync members with your email marketing platform (Mailchimp, ConvertKit, etc.).', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>

        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <h3><?php esc_html_e('Advanced Reports', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Get detailed analytics on revenue, churn, lifetime value, and more.', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>

        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <h3><?php esc_html_e('Content Dripping', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Release content to members on a schedule based on signup date.', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>

        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h3><?php esc_html_e('Member Directory', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Create a searchable directory of members with profiles and social links.', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>

        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <h3><?php esc_html_e('Advanced Access Rules', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Set up complex access rules based on categories, tags, custom post types, and more.', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>

        <div class="khm-extension-card">
            <div class="khm-extension-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <h3><?php esc_html_e('Additional Payment Gateways', 'khm-membership'); ?></h3>
            <p><?php esc_html_e('Accept payments via PayPal, Braintree, Square, and more.', 'khm-membership'); ?></p>
            <div class="khm-extension-footer">
                <span class="khm-badge">Coming Soon</span>
            </div>
        </div>
    </div>

    <div class="khm-extension-cta">
        <h2><?php esc_html_e('Want to Build an Extension?', 'khm-membership'); ?></h2>
        <p><?php esc_html_e('KHM Membership is built with extensibility in mind. Check out our developer documentation to learn how to create your own extensions.', 'khm-membership'); ?></p>
        <a href="https://github.com/yourusername/khm-plugin" class="button button-primary" target="_blank">
            <?php esc_html_e('View Documentation', 'khm-membership'); ?>
        </a>
    </div>
</div>

<style>
.khm-extensions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.khm-extension-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 25px;
    display: flex;
    flex-direction: column;
}

.khm-extension-icon {
    font-size: 48px;
    color: #2271b1;
    margin-bottom: 15px;
}

.khm-extension-icon .dashicons {
    width: 60px;
    height: 60px;
    font-size: 60px;
}

.khm-extension-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 18px;
}

.khm-extension-card p {
    flex-grow: 1;
    color: #646970;
    line-height: 1.6;
}

.khm-extension-footer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
}

.khm-extension-cta {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 25px 30px;
    margin-top: 30px;
    text-align: center;
}

.khm-extension-cta h2 {
    margin-top: 0;
}

.khm-extension-cta p {
    font-size: 16px;
    max-width: 700px;
    margin: 15px auto 25px;
}

@media (max-width: 782px) {
    .khm-extensions-grid {
        grid-template-columns: 1fr;
    }
}
</style>
