<?php
// Get data passed to the template
$membership = $data['membership'] ?? null;
?>

<div class="membership-dashboard" style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
    <h2>Your Membership</h2>

    <?php if ($membership): ?>
        <p><strong>Status:</strong> <?php echo esc_html($membership['status']); ?></p>
        <p><strong>Plan:</strong> <?php echo esc_html($membership['tier_name']); ?></p>
        
        <?php if ($membership['status'] !== 'cancelled'): ?>
            <button id="cancel-membership">Cancel Membership</button>
            <div id="cancel-response" style="margin-top: 10px;"></div>
        <?php endif; ?>

    <?php else: ?>
        <p>You do not have an active membership.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cancelButton = document.getElementById('cancel-membership');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            const responseDiv = document.getElementById('cancel-response');

            // In a real application, this would call a 'cancel' endpoint.
            // For now, we will just simulate it.
            responseDiv.innerHTML = '<p style="color: green;">Your membership has been cancelled.</p>';
            cancelButton.style.display = 'none';
        });
    }
});
</script>
