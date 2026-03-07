<?php
/**
 * Enhanced Email Service
 *
 * Provides professional email delivery with multiple methods (WordPress, SMTP, API)
 * Inspired by SendWP architecture but adapted for KHM plugin
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\EmailServiceInterface;

class EnhancedEmailService implements EmailServiceInterface {

    private string $from = '';
    private string $fromName = '';
    private string $subject = '';
    private array $headers = [];
    private array $attachments = [];
    private string $pluginDir;
    private string $templateDir = 'khm/email';
    
    // Email delivery methods
    const METHOD_WORDPRESS = 'wordpress';
    const METHOD_SMTP = 'smtp';
    const METHOD_API = 'api';
    
    // Email statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_QUEUED = 'queued';
    const STATUS_PROCESSING = 'processing';
    private const DEFAULT_MAX_QUEUE_ATTEMPTS = 3;
    private const DEFAULT_RETRY_BASE_SECONDS = 60;
    private const DEFAULT_QUEUE_BATCH_SIZE = 10;
    private const DEFAULT_QUEUE_MAX_RUNTIME_SECONDS = 20;
    private const DEFAULT_FAILED_QUEUE_CAP = 5000;

    public function __construct( string $pluginDir ) {
        $this->pluginDir = $pluginDir;
        $this->headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        
        // Initialize email delivery hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for email interception
     */
    private function init_hooks(): void {
        if ( $this->is_enhanced_delivery_enabled() ) {
            \add_action( 'phpmailer_init', [ $this, 'intercept_wp_mail' ] );
        }
        
        // Register cron job for email queue processing
        if ( $this->table_exists( $this->get_queue_table() ) && ! \wp_next_scheduled( 'khm_process_email_queue' ) ) {
            \wp_schedule_event( \time(), 'every_five_minutes', 'khm_process_email_queue' );
        }
        
        \add_action( 'khm_process_email_queue', [ $this, 'process_email_queue' ] );
    }

    /**
     * Send an email using a template.
     */
    public function send( string $templateKey, string $recipient, array $data = [] ): bool {
        $body = $this->render( $templateKey, $data );

        if ( empty( $body ) ) {
            \error_log( "KHM Enhanced Email: Template '$templateKey' rendered empty body" );
            return false;
        }

        // Get delivery method
        $delivery_method = $this->get_delivery_method();
        
        // Log email attempt
        $email_id = $this->log_email_attempt( $templateKey, $recipient, $data, $delivery_method );
        
        // Queue email for background processing if enabled
        if ( $this->should_queue_email() ) {
            return $this->queue_email( $email_id, $templateKey, $recipient, $body, $data );
        }
        
        // Send immediately
        return $this->send_email_now( $email_id, $recipient, $body );
    }

    /**
     * Render an email template without sending.
     */
    public function render( string $templateKey, array $data = [] ): string {
        $template = $this->load_template( $templateKey );
        
        if ( empty( $template ) ) {
            \error_log( "KHM Enhanced Email: Template '$templateKey' not found" );
            return '';
        }

        // Apply filters for customization
        $data = \apply_filters( 'khm_enhanced_email_data', $data, $templateKey );
        $template = \apply_filters( 'khm_enhanced_email_template', $template, $templateKey );

        // Replace variables
        $rendered = $this->replace_variables( $template, $data );
        
        return \apply_filters( 'khm_enhanced_email_body', $rendered, $templateKey, $data );
    }

    /**
     * Intercept WordPress mail for enhanced delivery
     */
    public function intercept_wp_mail( $phpmailer ): void {
        $delivery_method = $this->get_delivery_method();
        
        switch ( $delivery_method ) {
            case self::METHOD_SMTP:
                $this->configure_smtp( $phpmailer );
                break;
            case self::METHOD_API:
                // Replace with our API mailer
                $phpmailer = new EnhancedApiMailer( $phpmailer );
                break;
            default:
                // Use WordPress default but with tracking
                $this->add_tracking_to_phpmailer( $phpmailer );
                break;
        }
    }

    /**
     * Configure SMTP settings for PHPMailer
     */
    private function configure_smtp( $phpmailer ): void {
        $smtp_settings = $this->get_smtp_settings();
        
        if ( empty( $smtp_settings['host'] ) ) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_settings['host'];
        $phpmailer->Port = $smtp_settings['port'] ?? 587;
        $phpmailer->SMTPSecure = $smtp_settings['encryption'] ?? 'tls';
        
        if ( ! empty( $smtp_settings['username'] ) ) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_settings['username'];
            $phpmailer->Password = $smtp_settings['password'];
        }
        
        // Set from address if configured
        if ( ! empty( $smtp_settings['from_email'] ) ) {
            $phpmailer->setFrom( 
                $smtp_settings['from_email'], 
                $smtp_settings['from_name'] ?? \get_bloginfo( 'name' )
            );
        }
    }

    /**
     * Add email tracking to PHPMailer
     */
    private function add_tracking_to_phpmailer( $phpmailer ): void {
        // Add tracking pixel and click tracking
        \add_action( 'wp_mail_succeeded', [ $this, 'on_email_success' ] );
        \add_action( 'wp_mail_failed', [ $this, 'on_email_failure' ] );
    }

    /**
     * Send email immediately using configured method
     */
    private function send_email_now( int $email_id, string $recipient, string $body ): bool {
        $success = false;
        
        try {
            $delivery_method = $this->get_delivery_method();
            
            switch ( $delivery_method ) {
                case self::METHOD_API:
                    $success = $this->send_via_api( $recipient, $body );
                    break;
                case self::METHOD_SMTP:
                    $success = $this->send_via_smtp( $recipient, $body );
                    break;
                default:
                    $success = $this->send_via_wordpress( $recipient, $body );
                    break;
            }
            
            // Update email status
            $this->update_email_status( $email_id, $success ? self::STATUS_SENT : self::STATUS_FAILED );
            
        } catch ( \Exception $e ) {
            \error_log( "KHM Enhanced Email Error: " . $e->getMessage() );
            $this->update_email_status( $email_id, self::STATUS_FAILED, $e->getMessage() );
            $success = false;
        }
        
        return $success;
    }

    /**
     * Send email via API (SendGrid, Mailgun, etc.)
     */
    private function send_via_api( string $recipient, string $body ): bool {
        $api_settings = $this->get_api_settings();
        $provider = $api_settings['provider'] ?? 'sendgrid';
        
        switch ( $provider ) {
            case 'sendgrid':
                return $this->send_via_sendgrid( $recipient, $body, $api_settings );
            case 'mailgun':
                return $this->send_via_mailgun( $recipient, $body, $api_settings );
            default:
                return false;
        }
    }

    /**
     * Send via SendGrid API
     */
    private function send_via_sendgrid( string $recipient, string $body, array $settings ): bool {
        if ( empty( $settings['api_key'] ) ) {
            return false;
        }
        
        $data = [
            'personalizations' => [
                [
                    'to' => [ [ 'email' => $recipient ] ],
                    'subject' => $this->subject
                ]
            ],
            'from' => [
                'email' => $this->from ?: \get_option( 'admin_email' ),
                'name' => $this->fromName ?: \get_bloginfo( 'name' )
            ],
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $body
                ]
            ]
        ];
        
        $response = \wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json'
            ],
            'body' => \wp_json_encode( $data ),
            'timeout' => 30
        ] );
        
        return ! \is_wp_error( $response ) && \wp_remote_retrieve_response_code( $response ) === 202;
    }

    /**
     * Send via Mailgun API
     */
    private function send_via_mailgun( string $recipient, string $body, array $settings ): bool {
        if ( empty( $settings['api_key'] ) || empty( $settings['domain'] ) ) {
            return false;
        }
        
        $data = [
            'from' => $this->from ?: \get_option( 'admin_email' ),
            'to' => $recipient,
            'subject' => $this->subject,
            'html' => $body
        ];
        
        $response = \wp_remote_post( "https://api.mailgun.net/v3/{$settings['domain']}/messages", [
            'headers' => [
                'Authorization' => 'Basic ' . \base64_encode( 'api:' . $settings['api_key'] )
            ],
            'body' => $data,
            'timeout' => 30
        ] );
        
        return ! \is_wp_error( $response ) && \wp_remote_retrieve_response_code( $response ) === 200;
    }

    /**
     * Send via SMTP (using WordPress wp_mail with SMTP configuration)
     */
    private function send_via_smtp( string $recipient, string $body ): bool {
        return \wp_mail( $recipient, $this->subject, $body, $this->headers, $this->attachments );
    }

    /**
     * Send via WordPress default wp_mail
     */
    private function send_via_wordpress( string $recipient, string $body ): bool {
        return \wp_mail( $recipient, $this->subject, $body, $this->headers, $this->attachments );
    }

    /**
     * Queue email for background processing
     */
    private function queue_email( int $email_id, string $template, string $recipient, string $body, array $data ): bool {
        global $wpdb;
        
        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return false;
        }

        $idempotency_key = $this->build_queue_idempotency_key( $template, $recipient, $data );
        if ( $idempotency_key !== '' && $this->queue_entry_exists( $idempotency_key ) ) {
            return true;
        }

        $result = $wpdb->insert( $table, [
            'email_log_id' => $email_id,
            'idempotency_key' => $idempotency_key !== '' ? $idempotency_key : null,
            'template_key' => $template,
            'recipient' => $recipient,
            'subject' => $this->subject,
            'body' => $body,
            'headers' => \wp_json_encode( $this->headers ),
            'attachments' => \wp_json_encode( $this->attachments ),
            'data' => \wp_json_encode( $data ),
            'priority' => $this->get_email_priority( $template ),
            'max_attempts' => $this->get_max_queue_attempts(),
            'scheduled_at' => \current_time( 'mysql' ),
            'created_at' => \current_time( 'mysql' )
        ] );

        if ( false !== $result ) {
            return true;
        }

        return $idempotency_key !== '' && $this->queue_entry_exists( $idempotency_key );
    }

    /**
     * Process queued emails
     */
    public function process_email_queue(): void {
        global $wpdb;
        
        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return;
        }
        $batch_size = $this->get_queue_batch_size();
        $startedAt = \microtime( true );

        $this->cleanup_failed_queue_if_needed();
        
        // Get pending emails ordered by priority and schedule time
        $emails = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE status = 'pending' 
             AND scheduled_at <= %s 
             ORDER BY priority DESC, scheduled_at ASC 
             LIMIT %d",
            \current_time( 'mysql' ),
            $batch_size
        ) );
        
        foreach ( $emails as $email ) {
            if ( ( \microtime( true ) - $startedAt ) >= $this->get_queue_max_runtime_seconds() ) {
                \do_action( 'khm_email_queue_runtime_guard_triggered', [
                    'processed' => isset( $processed ) ? (int) $processed : 0,
                    'batch_size' => $batch_size,
                ] );
                break;
            }

            $this->process_queued_email( $email );
            $processed = isset( $processed ) ? $processed + 1 : 1;
        }

        \do_action( 'khm_email_queue_batch_completed', [
            'batch_size' => $batch_size,
            'processed' => isset( $processed ) ? (int) $processed : 0,
            'duration_ms' => (int) round( ( \microtime( true ) - $startedAt ) * 1000 ),
        ] );
    }

    /**
     * Process a single queued email
     */
    private function process_queued_email( $email ): void {
        global $wpdb;
        
        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return;
        }
        
        if ( isset( $email->status ) && (string) $email->status !== self::STATUS_PENDING ) {
            return;
        }

        $claimed = $wpdb->update(
            $table,
            [ 'status' => self::STATUS_PROCESSING, 'processed_at' => \current_time( 'mysql' ) ],
            [ 'id' => $email->id, 'status' => self::STATUS_PENDING ]
        );
        if ( false === $claimed || 0 === (int) $claimed ) {
            return;
        }
        
        // Restore email properties
        $this->subject = $email->subject;
        $this->headers = \json_decode( $email->headers, true ) ?: [];
        $this->attachments = \json_decode( $email->attachments, true ) ?: [];
        
        // Send email
        $success = $this->send_email_now( $email->email_log_id, $email->recipient, $email->body );
        $attempts = isset( $email->attempts ) ? (int) $email->attempts : 0;
        $max_attempts = isset( $email->max_attempts ) ? (int) $email->max_attempts : $this->get_max_queue_attempts();
        $max_attempts = max( 1, $max_attempts );

        if ( $success ) {
            $wpdb->update(
                $table,
                [
                    'status' => self::STATUS_SENT,
                    'sent_at' => \current_time( 'mysql' ),
                    'error' => null,
                ],
                [ 'id' => $email->id ]
            );
            return;
        }

        $attempts++;
        if ( $attempts < $max_attempts ) {
            $retry_delay = $this->calculate_retry_delay_seconds( $attempts );
            $scheduled_at = \gmdate( 'Y-m-d H:i:s', \time() + $retry_delay );
            $wpdb->update(
                $table,
                [
                    'status' => self::STATUS_PENDING,
                    'attempts' => $attempts,
                    'scheduled_at' => $scheduled_at,
                    'error' => 'Failed to send email (retry scheduled)',
                ],
                [ 'id' => $email->id ]
            );
            \do_action( 'khm_email_queue_retry_scheduled', (int) $email->id, $attempts, $retry_delay );
            return;
        }

        $wpdb->update(
            $table,
            [
                'status' => self::STATUS_FAILED,
                'attempts' => $attempts,
                'error' => 'Failed to send email (max retries reached)',
            ],
            [ 'id' => $email->id ]
        );
        \do_action( 'khm_email_queue_permanent_failure', (int) $email->id, $attempts );
    }

    private function get_max_queue_attempts(): int {
        $default = (int) \get_option( 'khm_email_queue_max_attempts', self::DEFAULT_MAX_QUEUE_ATTEMPTS );
        $value = (int) \apply_filters( 'khm_email_queue_max_attempts', $default );
        return max( 1, $value );
    }

    private function calculate_retry_delay_seconds( int $attempt_number ): int {
        $base = (int) \get_option( 'khm_email_queue_retry_base_seconds', self::DEFAULT_RETRY_BASE_SECONDS );
        $base = (int) \apply_filters( 'khm_email_queue_retry_base_seconds', $base );
        $base = max( 1, $base );

        // Exponential backoff with cap: base, base*2, base*4 ... up to 1 hour.
        $delay = (int) ( $base * ( 2 ** max( 0, $attempt_number - 1 ) ) );
        return min( 3600, $delay );
    }

    private function get_queue_batch_size(): int {
        $default = (int) \get_option( 'khm_email_queue_batch_size', self::DEFAULT_QUEUE_BATCH_SIZE );
        $default = max( 1, min( 200, $default ) );

        $adaptive = (int) \apply_filters( 'khm_email_queue_batch_size', $default );
        $adaptive = max( 1, min( 200, $adaptive ) );

        // If recent queue pressure is high, reduce batch to avoid DB/SMTP storms.
        $pending = $this->count_queue_rows_by_status( self::STATUS_PENDING );
        if ( $pending > 2000 ) {
            return max( 1, (int) floor( $adaptive / 2 ) );
        }

        return $adaptive;
    }

    private function get_queue_max_runtime_seconds(): int {
        $value = (int) \get_option( 'khm_email_queue_max_runtime_seconds', self::DEFAULT_QUEUE_MAX_RUNTIME_SECONDS );
        $value = (int) \apply_filters( 'khm_email_queue_max_runtime_seconds', $value );
        return max( 5, min( 120, $value ) );
    }

    private function count_queue_rows_by_status( string $status ): int {
        global $wpdb;

        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }

        $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status );
        return (int) $wpdb->get_var( $sql );
    }

    private function cleanup_failed_queue_if_needed(): void {
        global $wpdb;

        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return;
        }

        $cap = (int) \get_option( 'khm_email_failed_queue_cap', self::DEFAULT_FAILED_QUEUE_CAP );
        $cap = (int) \apply_filters( 'khm_email_failed_queue_cap', $cap );
        $cap = max( 100, $cap );

        $failedCount = $this->count_queue_rows_by_status( self::STATUS_FAILED );
        if ( $failedCount <= $cap ) {
            return;
        }

        $prune = $failedCount - $cap;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s ORDER BY updated_at ASC, id ASC LIMIT %d",
                self::STATUS_FAILED,
                $prune
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) || empty( $rows ) ) {
            return;
        }

        $deleted = 0;
        foreach ( $rows as $row ) {
            $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
            if ( $id <= 0 ) {
                continue;
            }
            $result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
            if ( is_numeric( $result ) && (int) $result > 0 ) {
                $deleted++;
            }
        }

        if ( $deleted > 0 ) {
            \do_action( 'khm_email_queue_dlq_capped', [
                'deleted' => $deleted,
                'failed_count_before' => $failedCount,
                'cap' => $cap,
            ] );
        }
    }

    /**
     * Get delivery method from settings
     */
    private function get_delivery_method(): string {
        return \get_option( 'khm_email_delivery_method', self::METHOD_WORDPRESS );
    }

    /**
     * Check if enhanced delivery is enabled
     */
    private function is_enhanced_delivery_enabled(): bool {
        return \get_option( 'khm_email_enhanced_delivery', false );
    }

    /**
     * Check if emails should be queued
     */
    private function should_queue_email(): bool {
        return \get_option( 'khm_email_use_queue', false )
            && $this->table_exists( $this->get_queue_table() );
    }

    /**
     * Get SMTP settings
     */
    private function get_smtp_settings(): array {
        return [
            'host' => \get_option( 'khm_smtp_host', '' ),
            'port' => \get_option( 'khm_smtp_port', 587 ),
            'encryption' => \get_option( 'khm_smtp_encryption', 'tls' ),
            'username' => \get_option( 'khm_smtp_username', '' ),
            'password' => \get_option( 'khm_smtp_password', '' ),
            'from_email' => \get_option( 'khm_smtp_from_email', '' ),
            'from_name' => \get_option( 'khm_smtp_from_name', '' )
        ];
    }

    /**
     * Get API settings
     */
    private function get_api_settings(): array {
        return [
            'provider' => \get_option( 'khm_email_api_provider', 'sendgrid' ),
            'api_key' => \get_option( 'khm_email_api_key', '' ),
            'domain' => \get_option( 'khm_email_api_domain', '' ) // For Mailgun
        ];
    }

    /**
     * Log email attempt
     */
    private function log_email_attempt( string $template, string $recipient, array $data, string $method ): int {
        global $wpdb;
        
        $table = $this->get_logs_table();
        if ( ! $this->table_exists( $table ) ) {
            return 0;
        }
        
        $wpdb->insert( $table, [
            'template_key' => $template,
            'recipient' => $recipient,
            'subject' => $this->subject,
            'delivery_method' => $method,
            'status' => self::STATUS_PENDING,
            'data' => \wp_json_encode( $data ),
            'created_at' => \current_time( 'mysql' )
        ] );
        
        return $wpdb->insert_id;
    }

    /**
     * Update email status
     */
    private function update_email_status( int $email_id, string $status, string $error = null ): void {
        global $wpdb;
        
        $table = $this->get_logs_table();
        if ( ! $this->table_exists( $table ) ) {
            return;
        }
        
        $data = [
            'status' => $status,
            'updated_at' => \current_time( 'mysql' )
        ];
        
        if ( $status === self::STATUS_SENT ) {
            $data['sent_at'] = \current_time( 'mysql' );
        }
        
        if ( $error ) {
            $data['error_message'] = $error;
        }
        
        $wpdb->update( $table, $data, [ 'id' => $email_id ] );
    }

    /**
     * Get email priority based on template
     */
    private function get_email_priority( string $template ): int {
        $priorities = [
            'gift_notification' => 10,  // High priority
            'checkout_paid' => 8,
            'welcome' => 5,
            'newsletter' => 1           // Low priority
        ];
        
        return $priorities[ $template ] ?? 5;
    }

    private function get_queue_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'khm_email_queue';
    }

    private function build_queue_idempotency_key( string $template, string $recipient, array $data ): string {
        if ( isset( $data['idempotency_key'] ) && $data['idempotency_key'] !== '' ) {
            return substr( sanitize_text_field( (string) $data['idempotency_key'] ), 0, 255 );
        }

        $reference = '';
        foreach ( [ 'event_id', 'reference', 'id_reference' ] as $key ) {
            if ( isset( $data[ $key ] ) && $data[ $key ] !== '' ) {
                $reference = sanitize_key( str_replace( ':', '_', (string) $data[ $key ] ) );
                break;
            }
        }

        if ( $reference === '' && isset( $data['schedule_id'] ) && $data['schedule_id'] !== '' ) {
            $reference = 'schedule_' . absint( $data['schedule_id'] );
        }

        if ( $reference === '' ) {
            $reference = substr( sha1( $recipient . '|' . $template . '|' . wp_json_encode( $data ) ), 0, 40 );
        }

        return substr( sprintf( '%s:%s', sanitize_key( $template ), $reference ), 0, 255 );
    }

    private function queue_entry_exists( string $idempotency_key ): bool {
        if ( $idempotency_key === '' ) {
            return false;
        }

        global $wpdb;
        $table = $this->get_queue_table();
        if ( ! $this->table_exists( $table ) ) {
            return false;
        }

        $rows = $wpdb->get_results( "SELECT * FROM {$table} LIMIT 1000", ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return false;
        }

        foreach ( $rows as $row ) {
            if ( (string) ( $row['idempotency_key'] ?? '' ) === $idempotency_key ) {
                return true;
            }
        }

        return false;
    }

    private function get_logs_table(): string {
        global $wpdb;

        return $wpdb->prefix . 'khm_email_logs';
    }

    private function table_exists( string $table ): bool {
        static $cache = array();

        if ( isset( $cache[ $table ] ) ) {
            return $cache[ $table ];
        }

        global $wpdb;
        $found = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        $cache[ $table ] = ( $found === $table );
        return $cache[ $table ];
    }

    // Fluent interface methods (matching EmailServiceInterface)
    
    public function setFrom( string $email, string $name = '' ): self {
        $this->from = $email;
        $this->fromName = $name;
        return $this;
    }

    public function setSubject( string $subject ): self {
        $this->subject = $subject;
        return $this;
    }

    public function setHeaders( array $headers ): self {
        $this->headers = $headers;
        return $this;
    }

    public function addAttachment( string $filePath ): self {
        $this->attachments[] = $filePath;
        return $this;
    }

    /**
     * Get the path to a template file.
     */
    public function getTemplatePath( string $templateKey ): ?string {
        $template_paths = $this->get_template_hierarchy( $templateKey );
        
        foreach ( $template_paths as $path ) {
            if ( \file_exists( $path ) ) {
                return $path;
            }
        }
        
        return null;
    }

    /**
     * Load email template from hierarchy
     */
    private function load_template( string $templateKey ): string {
        $template_paths = $this->get_template_hierarchy( $templateKey );
        
        foreach ( $template_paths as $path ) {
            if ( \file_exists( $path ) ) {
                return \file_get_contents( $path );
            }
        }
        
        return '';
    }

    /**
     * Get template hierarchy (theme -> plugin)
     */
    private function get_template_hierarchy( string $templateKey ): array {
        $locale = \get_locale();
        $paths = [];
        
        // Theme overrides
        $theme_dir = \get_stylesheet_directory();
        $parent_theme_dir = \get_template_directory();
        
        // Child theme with locale
        $paths[] = "{$theme_dir}/{$this->templateDir}/{$locale}/{$templateKey}.html";
        $paths[] = "{$theme_dir}/{$this->templateDir}/{$templateKey}.html";
        
        // Parent theme with locale
        if ( $parent_theme_dir !== $theme_dir ) {
            $paths[] = "{$parent_theme_dir}/{$this->templateDir}/{$locale}/{$templateKey}.html";
            $paths[] = "{$parent_theme_dir}/{$this->templateDir}/{$templateKey}.html";
        }
        
        // Plugin templates
        $paths[] = "{$this->pluginDir}/email/{$locale}/{$templateKey}.html";
        $paths[] = "{$this->pluginDir}/email/{$templateKey}.html";
        
        return $paths;
    }

    /**
     * Replace template variables
     */
    private function replace_variables( string $template, array $data ): string {
        // Add default WordPress variables
        $defaults = [
            'sitename' => \get_bloginfo( 'name' ),
            'siteurl' => \get_site_url(),
            'admin_email' => \get_option( 'admin_email' ),
            'date' => \date( 'Y-m-d' ),
            'time' => \date( 'H:i:s' )
        ];
        
        $data = \array_merge( $defaults, $data );
        
        // Replace !!variable!! syntax
        foreach ( $data as $key => $value ) {
            if ( \is_string( $value ) || \is_numeric( $value ) ) {
                $template = \str_replace( "!!{$key}!!", $value, $template );
            }
        }
        
        return $template;
    }

    /**
     * Handle email success
     */
    public function on_email_success( $mail_data ): void {
        \do_action( 'khm_enhanced_email_sent', $mail_data );
    }

    /**
     * Handle email failure
     */
    public function on_email_failure( $error ): void {
        \do_action( 'khm_enhanced_email_failed', $error );
        \error_log( 'KHM Enhanced Email Failed: ' . $error->get_error_message() );
    }
}
