<?php
/**
 * Email Service Interface
 *
 * Defines the contract for email rendering and delivery.
 * Supports template loading, variable replacement, and locale fallbacks.
 *
 * @package KHM\Contracts
 */

namespace KHM\Contracts;

interface EmailServiceInterface {

    /**
     * Send an email using a template.
     *
     * @param string $templateKey Template identifier (e.g., 'checkout_paid', 'cancel')
     * @param string $recipient Email address
     * @param array $data Template variables (name, membership_level, etc.)
     * @return bool True if email sent successfully
     */
    public function send( string $templateKey, string $recipient, array $data = [] ): bool;

    /**
     * Render an email template without sending.
     *
     * Useful for previews or testing.
     *
     * @param string $templateKey Template identifier
     * @param array $data Template variables
     * @return string Rendered HTML
     */
    public function render( string $templateKey, array $data = [] ): string;

    /**
     * Set the sender email and name.
     *
     * @param string $email From email address
     * @param string $name From name
     * @return self Fluent interface
     */
    public function setFrom( string $email, string $name ): self;

    /**
     * Set email headers.
     *
     * @param array $headers Array of headers (e.g., ['Reply-To: support@example.com'])
     * @return self Fluent interface
     */
    public function setHeaders( array $headers ): self;

    /**
     * Add an attachment.
     *
     * @param string $filePath Full path to attachment file
     * @return self Fluent interface
     */
    public function addAttachment( string $filePath ): self;

    /**
     * Set the email subject.
     *
     * @param string $subject Subject line
     * @return self Fluent interface
     */
    public function setSubject( string $subject ): self;

    /**
     * Get the path to a template file.
     *
     * Checks theme overrides, locale folders, and plugin defaults.
     *
     * @param string $templateKey Template identifier
     * @return string|null Full path to template file or null if not found
     */
    public function getTemplatePath( string $templateKey ): ?string;
}
