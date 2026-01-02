<?php
/**
 * Enhanced Email System Database Migration
 *
 * Creates tables for email logging, queue management, and delivery tracking
 *
 * @package KHM\Migrations
 */

namespace KHM\Migrations;

class EnhancedEmailMigration {

    /**
     * Create all enhanced email tables
     */
    public static function create_tables(): void {
        self::create_email_logs_table();
        self::create_email_queue_table();
        self::create_email_templates_table();
        self::create_email_stats_table();
    }

    /**
     * Drop all enhanced email tables
     */
    public static function drop_tables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'khm_email_logs',
            $wpdb->prefix . 'khm_email_queue',
            $wpdb->prefix . 'khm_email_templates',
            $wpdb->prefix . 'khm_email_stats'
        ];
        
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
    }

    /**
     * Create email logs table for delivery tracking
     */
    private static function create_email_logs_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_email_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_key varchar(100) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject text NOT NULL,
            delivery_method varchar(50) NOT NULL DEFAULT 'wordpress',
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority int(11) NOT NULL DEFAULT 5,
            data longtext,
            error_message text,
            opens int(11) NOT NULL DEFAULT 0,
            clicks int(11) NOT NULL DEFAULT 0,
            last_opened_at datetime,
            last_clicked_at datetime,
            created_at datetime NOT NULL,
            sent_at datetime,
            updated_at datetime,
            PRIMARY KEY (id),
            KEY recipient (recipient),
            KEY template_key (template_key),
            KEY status (status),
            KEY created_at (created_at),
            KEY delivery_method (delivery_method)
        ) {$charset_collate};";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        \dbDelta( $sql );
    }

    /**
     * Create email queue table for background processing
     */
    private static function create_email_queue_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_email_queue';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email_log_id bigint(20) unsigned NOT NULL,
            template_key varchar(100) NOT NULL,
            recipient varchar(255) NOT NULL,
            subject text NOT NULL,
            body longtext NOT NULL,
            headers longtext,
            attachments longtext,
            data longtext,
            priority int(11) NOT NULL DEFAULT 5,
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error text,
            scheduled_at datetime NOT NULL,
            processed_at datetime,
            sent_at datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY email_log_id (email_log_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY priority (priority),
            KEY template_key (template_key),
            FOREIGN KEY (email_log_id) REFERENCES {$wpdb->prefix}khm_email_logs(id) ON DELETE CASCADE
        ) {$charset_collate};";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        \dbDelta( $sql );
    }

    /**
     * Create email templates table for custom templates
     */
    private static function create_email_templates_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_email_templates';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_key varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            subject text NOT NULL,
            body longtext NOT NULL,
            description text,
            locale varchar(10) NOT NULL DEFAULT 'en_US',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY template_locale (template_key, locale),
            KEY is_active (is_active)
        ) {$charset_collate};";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        \dbDelta( $sql );
        
        // Insert default templates
        self::insert_default_templates();
    }

    /**
     * Create email statistics table for reporting
     */
    private static function create_email_stats_table(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_email_stats';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            template_key varchar(100) NOT NULL,
            delivery_method varchar(50) NOT NULL,
            sent_count int(11) NOT NULL DEFAULT 0,
            delivered_count int(11) NOT NULL DEFAULT 0,
            failed_count int(11) NOT NULL DEFAULT 0,
            bounced_count int(11) NOT NULL DEFAULT 0,
            opened_count int(11) NOT NULL DEFAULT 0,
            clicked_count int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY date_template_method (date, template_key, delivery_method),
            KEY date (date),
            KEY template_key (template_key)
        ) {$charset_collate};";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        \dbDelta( $sql );
    }

    /**
     * Insert default email templates
     */
    private static function insert_default_templates(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'khm_email_templates';
        
        $default_templates = [
            [
                'template_key' => 'gift_notification',
                'name' => 'Gift Notification',
                'subject' => 'You\'ve received a gift article from !!sender_name!!',
                'description' => 'Email sent when someone gifts an article',
                'body' => self::get_gift_notification_template()
            ],
            [
                'template_key' => 'welcome',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to !!sitename!!',
                'description' => 'Welcome email for new users',
                'body' => self::get_welcome_template()
            ],
            [
                'template_key' => 'checkout_paid',
                'name' => 'Purchase Confirmation',
                'subject' => 'Your purchase confirmation - !!sitename!!',
                'description' => 'Email sent after successful purchase',
                'body' => self::get_checkout_paid_template()
            ],
            [
                'template_key' => 'test_email',
                'name' => 'Test Email',
                'subject' => 'Test Email from !!sitename!!',
                'description' => 'Test email for configuration validation',
                'body' => self::get_test_email_template()
            ]
        ];
        
        foreach ( $default_templates as $template ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE template_key = %s AND locale = 'en_US'",
                $template['template_key']
            ) );
            
            if ( ! $existing ) {
                $wpdb->insert( $table_name, [
                    'template_key' => $template['template_key'],
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'description' => $template['description'],
                    'locale' => 'en_US',
                    'is_active' => 1,
                    'created_at' => \current_time( 'mysql' )
                ] );
            }
        }
    }

    /**
     * Get gift notification template
     */
    private static function get_gift_notification_template(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Notification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
        .btn { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .gift-message { background: #f1f3f4; padding: 20px; border-left: 4px solid #007cba; margin: 20px 0; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎁 You\'ve Received a Gift!</h1>
        <p>From !!sitename!!</p>
    </div>
    
    <div class="content">
        <p>Hi !!recipient_name!!,</p>
        
        <p><strong>!!sender_name!!</strong> has gifted you an article: <strong>!!article_title!!</strong></p>
        
        !!gift_message_section!!
        
        <p>Your gift includes access to:</p>
        <ul>
            <li>Full article content</li>
            <li>PDF download</li>
            <li>Save to your personal library</li>
        </ul>
        
        <div style="text-align: center;">
            <a href="!!redemption_url!!" class="btn">Redeem Your Gift</a>
        </div>
        
        <p><small>This gift will expire on !!expiry_date!!. Make sure to redeem it before then!</small></p>
        
        <p>Enjoy your reading!</p>
        
        <p>Best regards,<br>
        The !!sitename!! Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent from !!sitename!! (!!siteurl!!)</p>
        <p>If you have any questions, please contact us at !!admin_email!!</p>
    </div>
</body>
</html>';
    }

    /**
     * Get welcome template
     */
    private static function get_welcome_template(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to !!sitename!!</h1>
    </div>
    
    <div class="content">
        <p>Hi !!user_name!!,</p>
        
        <p>Welcome to !!sitename!! We\'re excited to have you as part of our community!</p>
        
        <p>Best regards,<br>
        The !!sitename!! Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent from !!sitename!! (!!siteurl!!)</p>
    </div>
</body>
</html>';
    }

    /**
     * Get checkout paid template
     */
    private static function get_checkout_paid_template(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Purchase Confirmation</h1>
        <p>Order #!!order_id!!</p>
    </div>
    
    <div class="content">
        <p>Hi !!user_name!!,</p>
        
        <p>Thank you for your purchase! Your payment has been processed successfully.</p>
        
        <p><strong>Amount:</strong> !!amount!!</p>
        <p><strong>Date:</strong> !!date!!</p>
        
        <p>Best regards,<br>
        The !!sitename!! Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent from !!sitename!! (!!siteurl!!)</p>
    </div>
</body>
</html>';
    }

    /**
     * Get test email template
     */
    private static function get_test_email_template(): string {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: white; padding: 30px; border: 1px solid #dee2e6; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; color: #6c757d; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Email Configuration Test</h1>
    </div>
    
    <div class="content">
        <div class="success">
            <strong>Success!</strong> Your enhanced email system is working correctly.
        </div>
        
        <p>This test email confirms that your enhanced email delivery system is properly configured and functioning.</p>
        
        <p><strong>Delivery Method:</strong> !!delivery_method!!</p>
        <p><strong>Test Time:</strong> !!date!! at !!time!!</p>
        
        <p>Your emails are now being sent with improved deliverability and tracking capabilities.</p>
        
        <p>Best regards,<br>
        Enhanced Email System</p>
    </div>
    
    <div class="footer">
        <p>This test email was sent from !!sitename!! (!!siteurl!!)</p>
        <p>Enhanced Email System by KHM Plugin</p>
    </div>
</body>
</html>';
    }

    /**
     * Check if tables exist
     */
    public static function tables_exist(): bool {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'khm_email_logs',
            $wpdb->prefix . 'khm_email_queue',
            $wpdb->prefix . 'khm_email_templates',
            $wpdb->prefix . 'khm_email_stats'
        ];
        
        foreach ( $tables as $table ) {
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
            if ( $table_exists !== $table ) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get database version for migrations
     */
    public static function get_db_version(): string {
        return \get_option( 'khm_enhanced_email_db_version', '0.0.0' );
    }

    /**
     * Update database version
     */
    public static function update_db_version( string $version ): void {
        \update_option( 'khm_enhanced_email_db_version', $version );
    }
}