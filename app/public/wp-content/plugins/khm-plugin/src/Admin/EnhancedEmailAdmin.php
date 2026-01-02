<?php
/**
 * Enhanced Email Settings Admin
 *
 * Provides admin interface for configuring enhanced email delivery
 * Inspired by SendWP admin interface but adapted for KHM plugin
 *
 * @package KHM\Admin
 */

namespace KHM\Admin;

use KHM\Services\EnhancedEmailService;
use KHM\Migrations\EnhancedEmailMigration;

class EnhancedEmailAdmin {

    private $email_service;
    private $page_slug = 'khm-enhanced-email';

    public function __construct( EnhancedEmailService $email_service ) {
        $this->email_service = $email_service;
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        \add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        \add_action( 'admin_init', [ $this, 'register_settings' ] );
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // AJAX handlers
        \add_action( 'wp_ajax_khm_test_email', [ $this, 'handle_test_email' ] );
        \add_action( 'wp_ajax_khm_email_stats', [ $this, 'handle_email_stats' ] );
        \add_action( 'wp_ajax_khm_process_email_queue', [ $this, 'handle_process_queue' ] );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        \add_submenu_page(
            'tools.php',
            'Enhanced Email',
            'Enhanced Email',
            'manage_options',
            $this->page_slug,
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        // General settings
        \register_setting( 'khm_enhanced_email_general', 'khm_email_enhanced_delivery' );
        \register_setting( 'khm_enhanced_email_general', 'khm_email_delivery_method' );
        \register_setting( 'khm_enhanced_email_general', 'khm_email_use_queue' );
        \register_setting( 'khm_enhanced_email_general', 'khm_email_from_email' );
        \register_setting( 'khm_enhanced_email_general', 'khm_email_from_name' );
        
        // SMTP settings
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_host' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_port' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_encryption' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_username' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_password' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_from_email' );
        \register_setting( 'khm_enhanced_email_smtp', 'khm_smtp_from_name' );
        
        // API settings
        \register_setting( 'khm_enhanced_email_api', 'khm_email_api_provider' );
        \register_setting( 'khm_enhanced_email_api', 'khm_email_api_key' );
        \register_setting( 'khm_enhanced_email_api', 'khm_email_api_domain' );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ): void {
        if ( strpos( $hook, $this->page_slug ) === false ) {
            return;
        }
        
        \wp_enqueue_script(
            'khm-enhanced-email-admin',
            \plugins_url( 'assets/js/enhanced-email-admin.js', dirname( dirname( __FILE__ ) ) ),
            [ 'jquery' ],
            '1.0.0',
            true
        );
        
        \wp_enqueue_style(
            'khm-enhanced-email-admin',
            \plugins_url( 'assets/css/enhanced-email-admin.css', dirname( dirname( __FILE__ ) ) ),
            [],
            '1.0.0'
        );
        
        \wp_localize_script( 'khm-enhanced-email-admin', 'khmEmailAdmin', [
            'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
            'nonce' => \wp_create_nonce( 'khm_email_admin_nonce' ),
            'strings' => [
                'testEmailSent' => __( 'Test email sent successfully!', 'khm-plugin' ),
                'testEmailFailed' => __( 'Failed to send test email.', 'khm-plugin' ),
                'loading' => __( 'Loading...', 'khm-plugin' ),
                'queueProcessed' => __( 'Email queue processed successfully!', 'khm-plugin' ),
                'queueProcessFailed' => __( 'Failed to process email queue.', 'khm-plugin' )
            ]
        ] );
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        // Check if tables need to be created
        if ( ! EnhancedEmailMigration::tables_exist() ) {
            EnhancedEmailMigration::create_tables();
            EnhancedEmailMigration::update_db_version( '1.0.0' );
        }
        
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap khm-email-admin">
            <h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>
            
            <div class="khm-email-status">
                <?php $this->render_email_status(); ?>
            </div>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->page_slug; ?>&tab=general" 
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'General', 'khm-plugin' ); ?>
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=smtp" 
                   class="nav-tab <?php echo $active_tab === 'smtp' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'SMTP', 'khm-plugin' ); ?>
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=api" 
                   class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'API', 'khm-plugin' ); ?>
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=queue" 
                   class="nav-tab <?php echo $active_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Queue', 'khm-plugin' ); ?>
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=logs" 
                   class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Logs', 'khm-plugin' ); ?>
                </a>
                <a href="?page=<?php echo $this->page_slug; ?>&tab=stats" 
                   class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    <?php _e( 'Statistics', 'khm-plugin' ); ?>
                </a>
            </h2>
            
            <div class="khm-email-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'smtp':
                        $this->render_smtp_tab();
                        break;
                    case 'api':
                        $this->render_api_tab();
                        break;
                    case 'queue':
                        $this->render_queue_tab();
                        break;
                    case 'logs':
                        $this->render_logs_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    default:
                        $this->render_general_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render email status
     */
    private function render_email_status(): void {
        $is_enabled = \get_option( 'khm_email_enhanced_delivery', false );
        $delivery_method = \get_option( 'khm_email_delivery_method', 'wordpress' );
        $queue_enabled = \get_option( 'khm_email_use_queue', false );
        
        $status_class = $is_enabled ? 'notice-success' : 'notice-warning';
        $status_text = $is_enabled 
            ? sprintf( __( 'Enhanced email delivery is ENABLED using %s method', 'khm-plugin' ), $delivery_method )
            : __( 'Enhanced email delivery is DISABLED', 'khm-plugin' );
        ?>
        <div class="notice <?php echo $status_class; ?> is-dismissible">
            <p><strong><?php echo $status_text; ?></strong></p>
            <?php if ( $queue_enabled ): ?>
                <p><?php _e( 'Email queue processing is enabled for background delivery.', 'khm-plugin' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render general settings tab
     */
    private function render_general_tab(): void {
        ?>
        <form method="post" action="options.php">
            <?php
            \settings_fields( 'khm_enhanced_email_general' );
            \do_settings_sections( 'khm_enhanced_email_general' );
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Enhanced Email Delivery', 'khm-plugin' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_email_enhanced_delivery" value="1" 
                                   <?php \checked( \get_option( 'khm_email_enhanced_delivery' ), 1 ); ?> />
                            <?php _e( 'Enable enhanced email delivery system', 'khm-plugin' ); ?>
                        </label>
                        <p class="description">
                            <?php _e( 'When enabled, all WordPress emails will be processed through the enhanced delivery system.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Delivery Method', 'khm-plugin' ); ?></th>
                    <td>
                        <select name="khm_email_delivery_method">
                            <option value="wordpress" <?php \selected( \get_option( 'khm_email_delivery_method' ), 'wordpress' ); ?>>
                                <?php _e( 'WordPress (Default)', 'khm-plugin' ); ?>
                            </option>
                            <option value="smtp" <?php \selected( \get_option( 'khm_email_delivery_method' ), 'smtp' ); ?>>
                                <?php _e( 'SMTP', 'khm-plugin' ); ?>
                            </option>
                            <option value="api" <?php \selected( \get_option( 'khm_email_delivery_method' ), 'api' ); ?>>
                                <?php _e( 'API (SendGrid/Mailgun)', 'khm-plugin' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e( 'Choose how emails should be delivered.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Use Email Queue', 'khm-plugin' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="khm_email_use_queue" value="1" 
                                   <?php \checked( \get_option( 'khm_email_use_queue' ), 1 ); ?> />
                            <?php _e( 'Process emails in background queue', 'khm-plugin' ); ?>
                        </label>
                        <p class="description">
                            <?php _e( 'Recommended for high-volume sites. Emails will be queued and processed every 5 minutes.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Default From Email', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="email" name="khm_email_from_email" 
                               value="<?php echo \esc_attr( \get_option( 'khm_email_from_email', \get_option( 'admin_email' ) ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'Default email address for outgoing emails.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Default From Name', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="text" name="khm_email_from_name" 
                               value="<?php echo \esc_attr( \get_option( 'khm_email_from_name', \get_bloginfo( 'name' ) ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'Default name for outgoing emails.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <div class="khm-email-test-section">
                <h3><?php _e( 'Test Email Configuration', 'khm-plugin' ); ?></h3>
                <p><?php _e( 'Send a test email to verify your configuration is working correctly.', 'khm-plugin' ); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Test Email Address', 'khm-plugin' ); ?></th>
                        <td>
                            <input type="email" id="test-email-address" 
                                   value="<?php echo \esc_attr( \wp_get_current_user()->user_email ); ?>" 
                                   class="regular-text" />
                            <button type="button" id="send-test-email" class="button button-secondary">
                                <?php _e( 'Send Test Email', 'khm-plugin' ); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                
                <div id="test-email-result"></div>
            </div>
            
            <?php \submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render SMTP settings tab
     */
    private function render_smtp_tab(): void {
        ?>
        <form method="post" action="options.php">
            <?php
            \settings_fields( 'khm_enhanced_email_smtp' );
            \do_settings_sections( 'khm_enhanced_email_smtp' );
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'SMTP Host', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="text" name="khm_smtp_host" 
                               value="<?php echo \esc_attr( \get_option( 'khm_smtp_host' ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'SMTP server hostname (e.g., smtp.gmail.com)', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'SMTP Port', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="number" name="khm_smtp_port" 
                               value="<?php echo \esc_attr( \get_option( 'khm_smtp_port', 587 ) ); ?>" 
                               class="small-text" />
                        <p class="description">
                            <?php _e( 'SMTP port (587 for TLS, 465 for SSL, 25 for unencrypted)', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Encryption', 'khm-plugin' ); ?></th>
                    <td>
                        <select name="khm_smtp_encryption">
                            <option value="tls" <?php \selected( \get_option( 'khm_smtp_encryption', 'tls' ), 'tls' ); ?>>
                                <?php _e( 'TLS', 'khm-plugin' ); ?>
                            </option>
                            <option value="ssl" <?php \selected( \get_option( 'khm_smtp_encryption' ), 'ssl' ); ?>>
                                <?php _e( 'SSL', 'khm-plugin' ); ?>
                            </option>
                            <option value="none" <?php \selected( \get_option( 'khm_smtp_encryption' ), 'none' ); ?>>
                                <?php _e( 'None', 'khm-plugin' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'SMTP Username', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="text" name="khm_smtp_username" 
                               value="<?php echo \esc_attr( \get_option( 'khm_smtp_username' ) ); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'SMTP Password', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="password" name="khm_smtp_password" 
                               value="<?php echo \esc_attr( \get_option( 'khm_smtp_password' ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'For Gmail, use an App Password instead of your regular password.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php \submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render API settings tab
     */
    private function render_api_tab(): void {
        ?>
        <form method="post" action="options.php">
            <?php
            \settings_fields( 'khm_enhanced_email_api' );
            \do_settings_sections( 'khm_enhanced_email_api' );
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'API Provider', 'khm-plugin' ); ?></th>
                    <td>
                        <select name="khm_email_api_provider" id="api-provider">
                            <option value="sendgrid" <?php \selected( \get_option( 'khm_email_api_provider', 'sendgrid' ), 'sendgrid' ); ?>>
                                <?php _e( 'SendGrid', 'khm-plugin' ); ?>
                            </option>
                            <option value="mailgun" <?php \selected( \get_option( 'khm_email_api_provider' ), 'mailgun' ); ?>>
                                <?php _e( 'Mailgun', 'khm-plugin' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'API Key', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="password" name="khm_email_api_key" 
                               value="<?php echo \esc_attr( \get_option( 'khm_email_api_key' ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'Your API key from SendGrid or Mailgun.', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="mailgun-only" style="<?php echo \get_option( 'khm_email_api_provider', 'sendgrid' ) === 'mailgun' ? '' : 'display: none;'; ?>">
                    <th scope="row"><?php _e( 'Mailgun Domain', 'khm-plugin' ); ?></th>
                    <td>
                        <input type="text" name="khm_email_api_domain" 
                               value="<?php echo \esc_attr( \get_option( 'khm_email_api_domain' ) ); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e( 'Your Mailgun domain (e.g., mg.yourdomain.com)', 'khm-plugin' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php \submit_button(); ?>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('#api-provider').change(function() {
                if ($(this).val() === 'mailgun') {
                    $('.mailgun-only').show();
                } else {
                    $('.mailgun-only').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render queue management tab
     */
    private function render_queue_tab(): void {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'khm_email_queue';
        $pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending'" );
        $processing_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$queue_table} WHERE status = 'processing'" );
        $failed_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$queue_table} WHERE status = 'failed'" );
        
        ?>
        <div class="khm-email-queue-stats">
            <div class="queue-stat">
                <span class="count"><?php echo \number_format( $pending_count ); ?></span>
                <span class="label"><?php _e( 'Pending', 'khm-plugin' ); ?></span>
            </div>
            <div class="queue-stat">
                <span class="count"><?php echo \number_format( $processing_count ); ?></span>
                <span class="label"><?php _e( 'Processing', 'khm-plugin' ); ?></span>
            </div>
            <div class="queue-stat">
                <span class="count"><?php echo \number_format( $failed_count ); ?></span>
                <span class="label"><?php _e( 'Failed', 'khm-plugin' ); ?></span>
            </div>
        </div>
        
        <div class="khm-email-queue-actions">
            <button type="button" id="process-queue-now" class="button button-primary">
                <?php _e( 'Process Queue Now', 'khm-plugin' ); ?>
            </button>
            <button type="button" id="clear-failed-queue" class="button button-secondary">
                <?php _e( 'Clear Failed Emails', 'khm-plugin' ); ?>
            </button>
        </div>
        
        <div id="queue-process-result"></div>
        
        <h3><?php _e( 'Recent Queue Items', 'khm-plugin' ); ?></h3>
        
        <?php
        $recent_items = $wpdb->get_results( 
            "SELECT * FROM {$queue_table} 
             ORDER BY created_at DESC 
             LIMIT 20"
        );
        
        if ( $recent_items ): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Template', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Recipient', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Subject', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Status', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Priority', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Scheduled', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Attempts', 'khm-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_items as $item ): ?>
                        <tr>
                            <td><?php echo \esc_html( $item->template_key ); ?></td>
                            <td><?php echo \esc_html( $item->recipient ); ?></td>
                            <td><?php echo \esc_html( \substr( $item->subject, 0, 50 ) . ( \strlen( $item->subject ) > 50 ? '...' : '' ) ); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo \esc_attr( $item->status ); ?>">
                                    <?php echo \esc_html( \ucfirst( $item->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo \esc_html( $item->priority ); ?></td>
                            <td><?php echo \esc_html( $item->scheduled_at ); ?></td>
                            <td><?php echo \esc_html( $item->attempts ); ?>/<?php echo \esc_html( $item->max_attempts ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e( 'No items in the email queue.', 'khm-plugin' ); ?></p>
        <?php endif;
    }

    /**
     * Render email logs tab
     */
    private function render_logs_tab(): void {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'khm_email_logs';
        $logs = $wpdb->get_results( 
            "SELECT * FROM {$logs_table} 
             ORDER BY created_at DESC 
             LIMIT 50"
        );
        
        ?>
        <h3><?php _e( 'Recent Email Logs', 'khm-plugin' ); ?></h3>
        
        <?php if ( $logs ): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Template', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Recipient', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Subject', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Method', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Status', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Created', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Sent', 'khm-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $logs as $log ): ?>
                        <tr>
                            <td><?php echo \esc_html( $log->template_key ); ?></td>
                            <td><?php echo \esc_html( $log->recipient ); ?></td>
                            <td><?php echo \esc_html( \substr( $log->subject, 0, 50 ) . ( \strlen( $log->subject ) > 50 ? '...' : '' ) ); ?></td>
                            <td><?php echo \esc_html( \ucfirst( $log->delivery_method ) ); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo \esc_attr( $log->status ); ?>">
                                    <?php echo \esc_html( \ucfirst( $log->status ) ); ?>
                                </span>
                            </td>
                            <td><?php echo \esc_html( $log->created_at ); ?></td>
                            <td><?php echo \esc_html( $log->sent_at ?: '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e( 'No email logs found.', 'khm-plugin' ); ?></p>
        <?php endif;
    }

    /**
     * Render statistics tab
     */
    private function render_stats_tab(): void {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'khm_email_logs';
        
        // Get overall stats
        $total_sent = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'sent'" );
        $total_failed = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'failed'" );
        $success_rate = $total_sent + $total_failed > 0 ? ( $total_sent / ( $total_sent + $total_failed ) ) * 100 : 0;
        
        // Get stats by delivery method
        $method_stats = $wpdb->get_results( 
            "SELECT delivery_method, 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$logs_table} 
             GROUP BY delivery_method"
        );
        
        ?>
        <div class="khm-email-stats-overview">
            <div class="stat-card">
                <div class="stat-number"><?php echo \number_format( $total_sent ); ?></div>
                <div class="stat-label"><?php _e( 'Emails Sent', 'khm-plugin' ); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo \number_format( $total_failed ); ?></div>
                <div class="stat-label"><?php _e( 'Failed', 'khm-plugin' ); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo \number_format( $success_rate, 1 ); ?>%</div>
                <div class="stat-label"><?php _e( 'Success Rate', 'khm-plugin' ); ?></div>
            </div>
        </div>
        
        <h3><?php _e( 'Delivery Method Performance', 'khm-plugin' ); ?></h3>
        
        <?php if ( $method_stats ): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Method', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Total', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Sent', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Failed', 'khm-plugin' ); ?></th>
                        <th><?php _e( 'Success Rate', 'khm-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $method_stats as $stat ): 
                        $method_success_rate = $stat->total > 0 ? ( $stat->sent / $stat->total ) * 100 : 0;
                    ?>
                        <tr>
                            <td><?php echo \esc_html( \ucfirst( $stat->delivery_method ) ); ?></td>
                            <td><?php echo \number_format( $stat->total ); ?></td>
                            <td><?php echo \number_format( $stat->sent ); ?></td>
                            <td><?php echo \number_format( $stat->failed ); ?></td>
                            <td><?php echo \number_format( $method_success_rate, 1 ); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e( 'No statistics available yet.', 'khm-plugin' ); ?></p>
        <?php endif;
    }

    /**
     * Handle test email AJAX request
     */
    public function handle_test_email(): void {
        \check_ajax_referer( 'khm_email_admin_nonce', 'nonce' );
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( 'Unauthorized' );
        }
        
        $email_address = \sanitize_email( $_POST['email'] ?? '' );
        
        if ( ! \is_email( $email_address ) ) {
            \wp_send_json_error( 'Invalid email address' );
        }
        
        $delivery_method = \get_option( 'khm_email_delivery_method', 'wordpress' );
        
        $result = $this->email_service->setSubject( 'Enhanced Email Test - ' . \get_bloginfo( 'name' ) )
                                    ->send( 'test_email', $email_address, [
                                        'delivery_method' => $delivery_method,
                                        'test_time' => \current_time( 'mysql' )
                                    ] );
        
        if ( $result ) {
            \wp_send_json_success( 'Test email sent successfully!' );
        } else {
            \wp_send_json_error( 'Failed to send test email.' );
        }
    }

    /**
     * Handle process queue AJAX request
     */
    public function handle_process_queue(): void {
        \check_ajax_referer( 'khm_email_admin_nonce', 'nonce' );
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die( 'Unauthorized' );
        }
        
        $this->email_service->process_email_queue();
        
        \wp_send_json_success( 'Email queue processed successfully!' );
    }
}