<?php
// Get data passed to the template
$schedule_id = $data['schedule_id'] ?? null;
$sponsor_id = $data['sponsor_id'] ?? null;
$user_phase = $data['user_phase'] ?? 'default';

// Mock sponsor data
$sponsor = null;
if ( $sponsor_id ) {
    $sponsor = [
        'name' => 'ACME Corp',
        'logo' => 'https://via.placeholder.com/150'
    ];
}
?>

<div class="landing-page" style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
    <?php if ($sponsor): ?>
        <div class="sponsor-branding" style="margin-bottom: 20px;">
            <h2>Sponsored by <?php echo esc_html($sponsor['name']); ?></h2>
            <img src="<?php echo esc_url($sponsor['logo']); ?>" alt="<?php echo esc_attr($sponsor['name']); ?> Logo">
        </div>
    <?php endif; ?>

    <h1>Your Exclusive Offer</h1>
    <p>Schedule ID: <?php echo esc_html($schedule_id); ?></p>
    <p>Your Engagement Phase: <?php echo esc_html($user_phase); ?></p>

    <div class="cta">
        <?php if ($user_phase === 'Acceptance'): ?>
            <button class="cta-button demo">Request a Demo</button>
        <?php elseif ($user_phase === 'Attention'): ?>
            <p>Learn more about our product below.</p>
        <?php else: ?>
            <div id="signup-form">
                <input type="email" id="email" placeholder="Enter your email">
                <button class="cta-button signup">Sign Up for a Free Trial</button>
            </div>
            <div id="signup-response" style="margin-top: 10px;"></div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const signupButton = document.querySelector('.cta-button.signup');
    if (signupButton) {
        signupButton.addEventListener('click', function() {
            const email = document.getElementById('email').value;
            const responseDiv = document.getElementById('signup-response');

            if (!email) {
                responseDiv.innerHTML = '<p style="color: red;">Please enter your email.</p>';
                return;
            }

            const data = {
                email: email,
                plan_id: 1, // Mock plan_id
                schedule_id: <?php echo json_encode($schedule_id); ?>
            };

            fetch('/wp-json/kh-membership/v1/signup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    responseDiv.innerHTML = '<p style="color: red;">' + data.error + '</p>';
                } else {
                    responseDiv.innerHTML = '<p style="color: green;">Success! Your user ID is ' + data.user_id + '</p>';
                }
            })
            .catch((error) => {
                responseDiv.innerHTML = '<p style="color: red;">An error occurred.</p>';
                console.error('Error:', error);
            });
        });
    }
});
</script>
