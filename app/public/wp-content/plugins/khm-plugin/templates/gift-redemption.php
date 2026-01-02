<?php
/**
 * Gift Redemption Page Template
 * 
 * This template handles gift redemption via URL token
 */

// Security check
defined('ABSPATH') || exit;

get_header();

// Get the token from URL
$token = sanitize_text_field($_GET['token'] ?? '');
$gift_data = null;
$error_message = '';

if (empty($token)) {
    $error_message = 'Invalid or missing gift token.';
} else {
    // Try to get gift data
    try {
        $gift_service = new KHM\Services\GiftService(
            new KHM\Services\MembershipRepository(),
            new KHM\Services\OrderRepository(),
            new KHM\Services\EmailService(__DIR__ . '/../')
        );
        
        $gift_data = $gift_service->get_gift_by_token($token);
        
        if (!$gift_data) {
            $error_message = 'Gift not found or token has expired.';
        } elseif ($gift_data['is_expired']) {
            $error_message = 'This gift has expired.';
        } elseif ($gift_data['is_redeemed']) {
            $error_message = 'This gift has already been redeemed.';
        }
    } catch (Exception $e) {
        $error_message = 'Unable to process gift redemption.';
        error_log('Gift redemption error: ' . $e->getMessage());
    }
}

$current_user_id = get_current_user_id();
?>

<div class="khm-gift-redemption-page">
    <div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
        
        <?php if ($error_message): ?>
            <!-- Error State -->
            <div class="gift-error" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border: 1px solid #f5c6cb; text-align: center;">
                <h2>Oops! Something went wrong</h2>
                <p><?php echo esc_html($error_message); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>" class="btn btn-primary">Return to Homepage</a></p>
            </div>
            
        <?php else: ?>
            <!-- Gift Valid - Show Redemption Options -->
            <div class="gift-redemption-container">
                <div class="gift-header" style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #667eea; margin-bottom: 10px;">🎁 You've Got a Gift!</h1>
                    <p style="font-size: 18px; color: #6c757d;">From <strong><?php echo esc_html($gift_data['sender_name']); ?></strong></p>
                </div>

                <div class="gift-article-info" style="background: #f8f9fa; padding: 30px; border-radius: 12px; margin-bottom: 30px; border-left: 4px solid #667eea;">
                    <h2 style="margin: 0 0 15px 0; color: #343a40;"><?php echo esc_html($gift_data['post_title']); ?></h2>
                    
                    <?php if (!empty($gift_data['post_excerpt'])): ?>
                        <p style="color: #6c757d; line-height: 1.6; margin-bottom: 20px;">
                            <?php echo esc_html($gift_data['post_excerpt']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($gift_data['gift_message'])): ?>
                        <div style="background: #e8f4f8; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h4 style="margin: 0 0 10px 0; color: #495057;">Personal Message:</h4>
                            <p style="margin: 0; font-style: italic; color: #495057;">
                                "<?php echo esc_html($gift_data['gift_message']); ?>"
                            </p>
                            <p style="margin: 10px 0 0 0; font-size: 14px; color: #6c757d;">
                                — <?php echo esc_html($gift_data['sender_name']); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="gift-redemption-options" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 20px 0; color: #343a40; text-align: center;">How would you like to enjoy your gift?</h3>
                    
                    <div class="redemption-buttons" style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
                        
                        <!-- Download PDF Option -->
                        <div class="redemption-option" style="flex: 1; min-width: 250px; text-align: center;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 10px;">
                                <h4 style="margin: 0 0 10px 0;">📄 Download PDF</h4>
                                <p style="margin: 0; font-size: 14px; opacity: 0.9;">Get a PDF copy for offline reading</p>
                            </div>
                            <button class="redeem-btn" data-type="download" data-token="<?php echo esc_attr($token); ?>" 
                                    style="background: #667eea; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%;">
                                Download Now
                            </button>
                        </div>

                        <?php if ($current_user_id > 0): ?>
                            <!-- Save to Library Option (Logged In Users) -->
                            <div class="redemption-option" style="flex: 1; min-width: 250px; text-align: center;">
                                <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 10px;">
                                    <h4 style="margin: 0 0 10px 0;">📚 Save to Library</h4>
                                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Add to your personal library for later</p>
                                </div>
                                <button class="redeem-btn" data-type="library_save" data-token="<?php echo esc_attr($token); ?>" 
                                        style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%;">
                                    Save to Library
                                </button>
                            </div>

                            <!-- Both Options -->
                            <div class="redemption-option" style="flex: 1; min-width: 250px; text-align: center;">
                                <div style="background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 10px;">
                                    <h4 style="margin: 0 0 10px 0;">⭐ Download + Save</h4>
                                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Get PDF and save to library</p>
                                </div>
                                <button class="redeem-btn" data-type="both" data-token="<?php echo esc_attr($token); ?>" 
                                        style="background: #fd7e14; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%;">
                                    Download + Save
                                </button>
                            </div>
                            
                        <?php else: ?>
                            <!-- Login Prompt for Non-Logged In Users -->
                            <div class="redemption-option" style="flex: 1; min-width: 250px; text-align: center;">
                                <div style="background: #6c757d; color: white; padding: 20px; border-radius: 8px; margin-bottom: 10px;">
                                    <h4 style="margin: 0 0 10px 0;">🔐 Create Free Account</h4>
                                    <p style="margin: 0; font-size: 14px; opacity: 0.9;">Login to save articles and access more features</p>
                                </div>
                                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" 
                                   style="display: inline-block; background: #6c757d; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; width: 100%; box-sizing: border-box;">
                                    Login / Register
                                </a>
                            </div>
                        <?php endif; ?>
                        
                    </div>

                    <div class="gift-info" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; text-align: center;">
                        <p style="font-size: 14px; color: #6c757d; margin: 0;">
                            This gift expires on <strong><?php echo date('F j, Y', strtotime($gift_data['expires_at'])); ?></strong>
                            (<?php echo $gift_data['days_until_expiry']; ?> days remaining)
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- AJAX JavaScript for Gift Redemption -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const redeemButtons = document.querySelectorAll('.redeem-btn');
    
    redeemButtons.forEach(button => {
        button.addEventListener('click', function() {
            const redemptionType = this.dataset.type;
            const token = this.dataset.token;
            
            // Disable button and show loading
            this.disabled = true;
            this.textContent = 'Processing...';
            
            // Make AJAX request
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'kss_redeem_gift',
                    token: token,
                    redemption_type: redemptionType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Gift redeemed successfully!');
                    
                    // If there's a download URL, start download
                    if (data.data.download_url) {
                        window.location.href = data.data.download_url;
                    }
                    
                    // Refresh page to show redeemed state
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Error: ' + (data.data ? data.data.error || data.data : 'Redemption failed'));
                    // Re-enable button
                    this.disabled = false;
                    this.textContent = getOriginalButtonText(redemptionType);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                // Re-enable button
                this.disabled = false;
                this.textContent = getOriginalButtonText(redemptionType);
            });
        });
    });
    
    function getOriginalButtonText(type) {
        switch(type) {
            case 'download': return 'Download Now';
            case 'library_save': return 'Save to Library';
            case 'both': return 'Download + Save';
            default: return 'Redeem';
        }
    }
});
</script>

<style>
/* Mobile responsive styles */
@media (max-width: 768px) {
    .redemption-buttons {
        flex-direction: column !important;
    }
    
    .redemption-option {
        min-width: 100% !important;
    }
}

.redeem-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.redeem-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
</style>

<?php get_footer(); ?>