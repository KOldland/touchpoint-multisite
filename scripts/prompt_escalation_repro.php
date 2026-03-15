<?php
wp_set_current_user(1);
$db = new Dual_GPT_DB_Handler();
$session_id = wp_generate_uuid4();
$article_id = wp_generate_uuid4();
$long_block = str_repeat('Operational execution detail backed by cited evidence. ', 240);
$framework = array(
    'positioning' => 'Evidence-led operations briefing',
    'narrative' => $long_block,
    'sections' => array(
        array('heading' => 'Context', 'body' => $long_block),
        array('heading' => 'Trade-offs', 'body' => $long_block),
    ),
);
$citations = array(
    array('title' => 'Field Service Benchmark 2025', 'url' => 'https://example.com/bench-2025', 'quote' => 'Teams cut MTTR by 18% with tighter dispatch policy.', 'lead_author' => 'A. Analyst', 'organisation' => 'Benchmark Lab', 'publication_date' => '2025'),
    array('title' => 'Operations Study', 'url' => 'https://example.com/ops-study', 'quote' => 'Standardization improved first-time-fix rates.', 'lead_author' => 'B. Researcher', 'organisation' => 'Ops Institute', 'publication_date' => '2024'),
    array('title' => 'Service Economics', 'url' => 'https://example.com/econ', 'quote' => 'Technician utilization is strongly linked to margin stability.', 'lead_author' => 'C. Economist', 'organisation' => 'Service Council', 'publication_date' => '2023'),
    array('title' => 'Scheduling Paper', 'url' => 'https://example.com/sched', 'quote' => 'Route optimization reduced overtime costs.', 'lead_author' => 'D. Planner', 'organisation' => 'Logistics Forum', 'publication_date' => '2022'),
    array('title' => 'Quality Review', 'url' => 'https://example.com/quality', 'quote' => 'Quality gates lowered repeat truck rolls.', 'lead_author' => 'E. Reviewer', 'organisation' => 'Quality Group', 'publication_date' => '2021'),
);
$meta = array(
    'articles' => array(
        array(
            'id' => $article_id,
            'title' => 'Synthetic Prompt Escalation Repro',
            'summary' => 'A controlled oversized framework payload to validate compact escalation.',
            'keywords' => array('field service', 'operations', 'dispatch'),
            'framework' => array('output' => $framework),
            'citations' => $citations,
        ),
    ),
);
$session_data = array(
    'id' => $session_id,
    'role' => 'research',
    'preset_id' => 'research-default',
    'title' => 'Prompt Escalation Repro Session',
    'status' => 'active',
    'created_by' => 1,
    'meta_json' => wp_json_encode($meta),
    'idempotency_key' => 'repro-compact-' . substr(md5($session_id), 0, 12),
);
$inserted = $db->insert_session($session_data);
if (is_wp_error($inserted)) {
    echo 'SEED_ERROR=' . $inserted->get_error_message() . "\n";
    return;
}

$plugin = null;
foreach ($GLOBALS as $value) {
    if (is_object($value) && $value instanceof Dual_GPT_Plugin) {
        $plugin = $value;
        break;
    }
}

$full_len = null;
$compact_len = null;
if ($plugin) {
    $method = new ReflectionMethod('Dual_GPT_Plugin', 'build_author_prompt');
    $method->setAccessible(true);
    $article_seed = $meta['articles'][0];
    $full_len = strlen($method->invoke($plugin, $article_seed, $framework, 'balanced', false));
    $compact_len = strlen($method->invoke($plugin, $article_seed, $framework, 'balanced', true));
}

$req = new WP_REST_Request('POST', '/dual-gpt/v1/planner/run-author');
$req->set_param('session_id', $session_id);
$req->set_param('article_id', $article_id);
$req->set_param('author_profile', '');
$req->set_param('retry_compact_prompt', false);
$res = rest_do_request($req);
$status = $res->get_status();
$data = $res->get_data();
$session = $db->get_session($session_id);
$meta_out = json_decode((string) ($session['meta_json'] ?? ''), true);
$author = $meta_out['articles'][0]['author'] ?? array();

echo 'SESSION_ID=' . $session_id . "\n";
echo 'ARTICLE_ID=' . $article_id . "\n";
echo 'HTTP_STATUS=' . $status . "\n";
if (is_array($data)) {
    if (isset($data['code'])) {
        echo 'RESP_CODE=' . $data['code'] . "\n";
    }
    if (isset($data['message'])) {
        echo 'RESP_MESSAGE=' . $data['message'] . "\n";
    }
    if (isset($data['job_id'])) {
        echo 'JOB_ID=' . $data['job_id'] . "\n";
    }
}
if ($full_len !== null) {
    echo 'FULL_PROMPT_LEN=' . $full_len . "\n";
}
if ($compact_len !== null) {
    echo 'COMPACT_PROMPT_LEN=' . $compact_len . "\n";
}
if (!empty($author)) {
    if (isset($author['prompt_size'])) {
        echo 'META_PROMPT_SIZE=' . $author['prompt_size'] . "\n";
    }
    if (isset($author['compact_tier'])) {
        echo 'META_COMPACT_TIER=' . $author['compact_tier'] . "\n";
    }
    if (isset($author['status'])) {
        echo 'META_AUTHOR_STATUS=' . $author['status'] . "\n";
    }
}
