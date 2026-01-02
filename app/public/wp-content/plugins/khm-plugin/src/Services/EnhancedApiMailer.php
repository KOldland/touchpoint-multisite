<?php
/**
 * Enhanced API Mailer
 *
 * A decorator for PHPMailer that redirects email through API services
 * Inspired by SendWP's approach but for KHM plugin
 *
 * @package KHM\Services
 */

namespace KHM\Services;

class EnhancedApiMailer {
    
    protected $phpmailer;
    protected $api_settings;

    public function __construct( $phpmailer ) {
        $this->phpmailer = $phpmailer;
        $this->api_settings = $this->get_api_settings();
    }

    /**
     * Check for property/method in $phpmailer.
     */
    public function __get( $name ) {
        if ( \property_exists( $this->phpmailer, $name ) ) {
            return $this->phpmailer->$name;
        }
        return '';
    }

    public function __call( $name, $arguments ) {
        if ( \method_exists( $this->phpmailer, $name ) ) {
            return \call_user_func_array( [ $this->phpmailer, $name ], $arguments );
        }
        return null;
    }

    /**
     * Override send method to use API
     */
    public function send(): bool {
        $provider = $this->api_settings['provider'] ?? 'sendgrid';
        
        switch ( $provider ) {
            case 'sendgrid':
                return $this->send_via_sendgrid();
            case 'mailgun':
                return $this->send_via_mailgun();
            default:
                // Fallback to original PHPMailer
                return $this->phpmailer->send();
        }
    }

    /**
     * Send via SendGrid API
     */
    private function send_via_sendgrid(): bool {
        if ( empty( $this->api_settings['api_key'] ) ) {
            return false;
        }

        $to_addresses = $this->format_addresses( $this->phpmailer->getToAddresses() );
        $cc_addresses = $this->format_addresses( $this->phpmailer->getCcAddresses() );
        $bcc_addresses = $this->format_addresses( $this->phpmailer->getBccAddresses() );

        $data = [
            'personalizations' => [
                [
                    'to' => $to_addresses,
                    'subject' => $this->phpmailer->Subject
                ]
            ],
            'from' => [
                'email' => $this->phpmailer->From,
                'name' => $this->phpmailer->FromName
            ],
            'content' => [
                [
                    'type' => $this->phpmailer->ContentType === 'text/html' ? 'text/html' : 'text/plain',
                    'value' => $this->phpmailer->Body
                ]
            ]
        ];

        // Add CC/BCC if present
        if ( ! empty( $cc_addresses ) ) {
            $data['personalizations'][0]['cc'] = $cc_addresses;
        }
        if ( ! empty( $bcc_addresses ) ) {
            $data['personalizations'][0]['bcc'] = $bcc_addresses;
        }

        // Add reply-to if set
        if ( ! empty( $this->phpmailer->getReplyToAddresses() ) ) {
            $reply_to = $this->format_addresses( $this->phpmailer->getReplyToAddresses() );
            $data['reply_to'] = $reply_to[0] ?? null;
        }

        // Add attachments
        if ( $attachments = $this->format_attachments() ) {
            $data['attachments'] = $attachments;
        }

        $response = \wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_settings['api_key'],
                'Content-Type' => 'application/json'
            ],
            'body' => \wp_json_encode( $data ),
            'timeout' => 30
        ] );

        $success = ! \is_wp_error( $response ) && \wp_remote_retrieve_response_code( $response ) === 202;
        
        if ( ! $success ) {
            $error_message = \is_wp_error( $response ) 
                ? $response->get_error_message() 
                : 'SendGrid API returned error code: ' . \wp_remote_retrieve_response_code( $response );
            
            $this->phpmailer->setError( $error_message );
        }

        return $success;
    }

    /**
     * Send via Mailgun API
     */
    private function send_via_mailgun(): bool {
        if ( empty( $this->api_settings['api_key'] ) || empty( $this->api_settings['domain'] ) ) {
            return false;
        }

        $to_addresses = $this->phpmailer->getToAddresses();
        $to_string = \implode( ',', \array_column( $to_addresses, 0 ) );

        $data = [
            'from' => $this->phpmailer->From,
            'to' => $to_string,
            'subject' => $this->phpmailer->Subject,
        ];

        // Set body based on content type
        if ( $this->phpmailer->ContentType === 'text/html' ) {
            $data['html'] = $this->phpmailer->Body;
            if ( ! empty( $this->phpmailer->AltBody ) ) {
                $data['text'] = $this->phpmailer->AltBody;
            }
        } else {
            $data['text'] = $this->phpmailer->Body;
        }

        // Add CC/BCC
        if ( $cc_addresses = $this->phpmailer->getCcAddresses() ) {
            $data['cc'] = \implode( ',', \array_column( $cc_addresses, 0 ) );
        }
        if ( $bcc_addresses = $this->phpmailer->getBccAddresses() ) {
            $data['bcc'] = \implode( ',', \array_column( $bcc_addresses, 0 ) );
        }

        // Add reply-to
        if ( $reply_to = $this->phpmailer->getReplyToAddresses() ) {
            $data['h:Reply-To'] = \array_column( $reply_to, 0 )[0];
        }

        $response = \wp_remote_post( "https://api.mailgun.net/v3/{$this->api_settings['domain']}/messages", [
            'headers' => [
                'Authorization' => 'Basic ' . \base64_encode( 'api:' . $this->api_settings['api_key'] )
            ],
            'body' => $data,
            'timeout' => 30
        ] );

        $success = ! \is_wp_error( $response ) && \wp_remote_retrieve_response_code( $response ) === 200;
        
        if ( ! $success ) {
            $error_message = \is_wp_error( $response ) 
                ? $response->get_error_message() 
                : 'Mailgun API returned error code: ' . \wp_remote_retrieve_response_code( $response );
            
            $this->phpmailer->setError( $error_message );
        }

        return $success;
    }

    /**
     * Format email addresses for API
     */
    private function format_addresses( array $addresses ): array {
        $formatted = [];
        foreach ( $addresses as $address ) {
            $formatted[] = [
                'email' => $address[0],
                'name' => $address[1] ?? ''
            ];
        }
        return $formatted;
    }

    /**
     * Format attachments for API
     */
    private function format_attachments(): array {
        $attachments = [];
        
        foreach ( $this->phpmailer->getAttachments() as $attachment ) {
            $file_path = $attachment[0];
            $file_name = $attachment[2];
            
            if ( \file_exists( $file_path ) ) {
                $attachments[] = [
                    'content' => \base64_encode( \file_get_contents( $file_path ) ),
                    'filename' => $file_name,
                    'type' => \mime_content_type( $file_path ),
                    'disposition' => 'attachment'
                ];
            }
        }
        
        return $attachments;
    }

    /**
     * Get API settings
     */
    private function get_api_settings(): array {
        return [
            'provider' => \get_option( 'khm_email_api_provider', 'sendgrid' ),
            'api_key' => \get_option( 'khm_email_api_key', '' ),
            'domain' => \get_option( 'khm_email_api_domain', '' )
        ];
    }
}