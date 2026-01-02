<?php
/**
 * Email Service
 *
 * Handles email template loading, rendering, and delivery.
 * Supports theme overrides and locale-specific templates.
 *
 * @package KHM\Services
 */

namespace KHM\Services;

use KHM\Contracts\EmailServiceInterface;

class EmailService implements EmailServiceInterface {

    private string $from = '';
    private string $fromName = '';
    private string $subject = '';
    private array $headers = [];
    private array $attachments = [];
    private string $pluginDir;
    private string $templateDir = 'khm/email';

    public function __construct( string $pluginDir ) {
        $this->pluginDir = $pluginDir;
        $this->headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    }

    /**
     * Send an email using a template.
     */
    public function send( string $templateKey, string $recipient, array $data = [] ): bool {
        $body = $this->render($templateKey, $data);

        if ( empty($body) ) {
            error_log("KHM Email: Template '{$templateKey}' not found or empty");
            return false;
        }

        // Apply filters
        $recipient = apply_filters('khm_email_recipient', $recipient, $templateKey, $data);
        $subject = apply_filters('khm_email_subject', $this->subject, $templateKey, $data);
        $body = apply_filters('khm_email_body', $body, $templateKey, $data);
        $headers = apply_filters('khm_email_headers', $this->headers, $templateKey, $data);
        $attachments = apply_filters('khm_email_attachments', $this->attachments, $templateKey, $data);

        // Set from header if configured
        if ( ! empty($this->from) ) {
            $fromName = ! empty($this->fromName) ? $this->fromName : $this->from;
            $headers[] = "From: {$fromName} <{$this->from}>";
        }

        // Send email
        $sent = wp_mail($recipient, $subject, $body, $headers, $attachments);

        // Fire action
        do_action('khm_email_sent', $sent, $templateKey, $recipient, $data);

        return $sent;
    }

    /**
     * Render an email template without sending.
     */
    public function render( string $templateKey, array $data = [] ): string {
        $templatePath = $this->getTemplatePath($templateKey);

        if ( ! $templatePath || ! file_exists($templatePath) ) {
            // Fallback to default template
            $templatePath = $this->getTemplatePath('default');
            if ( ! $templatePath ) {
                return '';
            }
        }

        // Load template content
        $body = file_get_contents($templatePath);

        // Load header and footer
        $header = $this->loadHeaderFooter('header');
        $footer = $this->loadHeaderFooter('footer');

        if ( $header ) {
            $body = $header . "\n" . $body;
        }
        if ( $footer ) {
            $body = $body . "\n" . $footer;
        }

        // Replace variables
        $body = $this->replaceVariables($body, $data);

        // Apply filter
        $body = apply_filters('khm_email_template', $body, $templateKey, $data);

        return $body;
    }

    /**
     * Set the sender email and name.
     */
    public function setFrom( string $email, string $name ): self {
        $this->from = $email;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Set email headers.
     */
    public function setHeaders( array $headers ): self {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add an attachment.
     */
    public function addAttachment( string $filePath ): self {
        if ( file_exists($filePath) ) {
            $this->attachments[] = $filePath;
        }
        return $this;
    }

    /**
     * Set the email subject.
     */
    public function setSubject( string $subject ): self {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Get the path to a template file.
     */
    public function getTemplatePath( string $templateKey ): ?string {
        $locale = apply_filters('plugin_locale', get_locale(), 'khm');
        $filename = $templateKey . '.html';

        // Template hierarchy (same as PMPro)
        $locations = [
            // Child theme with locale
            get_stylesheet_directory() . "/{$this->templateDir}/{$locale}/{$filename}",
            // Child theme
            get_stylesheet_directory() . "/{$this->templateDir}/{$filename}",
            // Parent theme with locale
            get_template_directory() . "/{$this->templateDir}/{$locale}/{$filename}",
            // Parent theme
            get_template_directory() . "/{$this->templateDir}/{$filename}",
            // WP language folder with locale
            WP_LANG_DIR . "/khm/email/{$locale}/{$filename}",
            // WP language folder
            WP_LANG_DIR . "/khm/email/{$filename}",
            // Plugin language folder with locale
            $this->pluginDir . "/languages/email/{$locale}/{$filename}",
            // Plugin email folder
            $this->pluginDir . "/email/{$filename}",
        ];

        foreach ( $locations as $path ) {
            if ( file_exists($path) ) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Load header or footer template.
     */
    private function loadHeaderFooter( string $type ): ?string {
        $path = $this->getTemplatePath($type);
        return $path && file_exists($path) ? file_get_contents($path) : null;
    }

    /**
     * Replace template variables (!!variable!!).
     */
    private function replaceVariables( string $content, array $data ): string {
        // Add default variables
        $defaults = [
            'sitename' => get_bloginfo('name'),
            'siteemail' => get_option('admin_email'),
            'siteurl' => home_url(),
            'login_url' => wp_login_url(),
        ];

        $data = array_merge($defaults, $data);

        // Apply data filter
        $data = apply_filters('khm_email_data', $data);

        // Replace !!key!! with value
        foreach ( $data as $key => $value ) {
            if ( is_scalar($value) ) {
                $content = str_replace("!!{$key}!!", $value, $content);
            }
        }

        return $content;
    }
}
