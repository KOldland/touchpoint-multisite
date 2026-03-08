<?php

declare(strict_types=1);

const FIXTURE_ROOT = __DIR__ . '/../golden-fixtures';

echo "Running Golden Fixture Replay\n";

function load_fixture(string $path): array {
    $fullPath = FIXTURE_ROOT . '/' . ltrim($path, '/');
    if (!is_file($fullPath)) {
        echo "Missing fixture: {$path}\n";
        exit(1);
    }

    $raw = file_get_contents($fullPath);
    if ($raw === false) {
        echo "Unable to read fixture: {$path}\n";
        exit(1);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        echo "Invalid JSON fixture: {$path}\n";
        exit(1);
    }

    assert_no_pii($decoded, $path);
    return $decoded;
}

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        echo "Assertion failed: {$message}\n";
        exit(1);
    }
}

function assert_no_pii(array $payload, string $fixturePath): void {
    $flat = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($flat)) {
        return;
    }

    assert_true((bool) (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $flat) !== 1), "PII detected in fixture {$fixturePath}");
    assert_true((bool) (preg_match('/\b\d{3}-\d{2}-\d{4}\b/', $flat) !== 1), "PII detected in fixture {$fixturePath}");

    if (preg_match_all('/\+?[0-9][0-9\-()\s]{7,}[0-9]/', $flat, $matches) === 1) {
        foreach ($matches[0] as $candidate) {
            $normalized = preg_replace('/[^0-9]/', '', (string) $candidate);
            if (!is_string($normalized)) {
                continue;
            }

            if (strlen($normalized) < 10) {
                continue;
            }

            if (preg_match('/^\d{8,14}$/', $normalized) === 1 && str_contains((string) $candidate, 'T')) {
                continue;
            }

            assert_true(false, "PII detected in fixture {$fixturePath}");
        }
    }
}

function simulate_membership_signup(array $signup): array {
    $seed = ($signup['user_ref'] ?? 'anon') . '|' . ($signup['plan_id'] ?? 'plan');
    return array(
        'session_id' => 'sess_' . substr(hash('sha256', $seed), 0, 12),
        'status' => 'initialized',
    );
}

function simulate_attribution(array $webhook, string $sessionId): array {
    $event = (string) ($webhook['event'] ?? '');
    assert_true($event === 'checkout.session.completed', 'Unexpected webhook event type');

    return array(
        'status' => 'recorded',
        'session_id' => (string) ($webhook['session_id'] ?? $sessionId),
        'sponsor_id' => (string) ($webhook['sponsor_id'] ?? ''),
    );
}

function simulate_smma_generation(array $request): array {
    $variantCount = isset($request['requested_variants']) ? (int) $request['requested_variants'] : 1;
    return array(
        'status' => 'generated',
        'variants_created' => max(1, $variantCount),
    );
}

function simulate_schedule_creation(array $schedule): array {
    $approvalStatus = (string) ($schedule['approval_status'] ?? 'pending');
    return array(
        'schedule_id' => (string) ($schedule['schedule_id'] ?? ''),
        'approval_status' => $approvalStatus,
    );
}

function simulate_sponsor_approval(array $approval): array {
    $decision = (string) ($approval['decision'] ?? '');
    assert_true($decision === 'approved', 'Approval decision is not approved');

    return array(
        'schedule_id' => (string) ($approval['schedule_id'] ?? ''),
        'approval_status' => 'approved',
    );
}

function simulate_paid_dry_run(array $manifest): array {
    $lineItems = $manifest['line_items'] ?? array();
    $total = 0;

    if (is_array($lineItems)) {
        foreach ($lineItems as $item) {
            if (is_array($item)) {
                $total += (int) ($item['budget_cents'] ?? 0);
            }
        }
    }

    return array(
        'estimated_spend' => round($total / 100, 2),
        'currency' => (string) ($manifest['currency'] ?? 'AUD'),
    );
}

function simulate_paid_execute(array $execute): array {
    $operationIds = $execute['operation_ids'] ?? array();
    return array(
        'status' => (string) ($execute['status'] ?? 'failed'),
        'operation_ids' => is_array($operationIds) ? $operationIds : array(),
    );
}

echo "Step 1: Membership signup\n";
$signup = load_fixture('membership/signup_init_request.json');
$signupResponse = simulate_membership_signup($signup);
assert_true(isset($signupResponse['session_id']) && $signupResponse['session_id'] !== '', 'Session ID missing');

echo "Step 2: Webhook attribution\n";
$webhook = load_fixture('membership/stripe_checkout_completed.json');
$attribution = simulate_attribution($webhook, $signupResponse['session_id']);
assert_true(($attribution['status'] ?? '') === 'recorded', 'Attribution not stored');

echo "Step 3: SMMA generation\n";
$generateRequest = load_fixture('smma/generate_request.json');
$generated = simulate_smma_generation($generateRequest);
assert_true(($generated['status'] ?? '') === 'generated', 'SMMA generation failed');

echo "Step 4: SMMA schedule creation\n";
$schedule = load_fixture('smma/schedule_create.json');
$scheduleResult = simulate_schedule_creation($schedule);
assert_true(($scheduleResult['approval_status'] ?? '') === 'pending', 'Schedule not pending approval');

echo "Step 5: Sponsor approval\n";
$approval = load_fixture('smma/approval_event.json');
$approvalResult = simulate_sponsor_approval($approval);
assert_true(($approvalResult['approval_status'] ?? '') === 'approved', 'Approval failed');

echo "Step 6: Paid adapter dry run\n";
$manifest = load_fixture('paid/dry_run_manifest.json');
$dryRun = simulate_paid_dry_run($manifest);
assert_true(isset($dryRun['estimated_spend']), 'Dry run failed');

echo "Step 7: Paid execute\n";
$execute = load_fixture('paid/execute_response.json');
$result = simulate_paid_execute($execute);
assert_true(($result['status'] ?? '') === 'ok', 'Execute failed');
assert_true(!empty($result['operation_ids']), 'Execute missing operation IDs');

echo "Replay Test Passed\n";
exit(0);