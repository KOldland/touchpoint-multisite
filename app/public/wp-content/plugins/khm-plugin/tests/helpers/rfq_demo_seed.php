<?php
/**
 * RFQ Demo Seeder
 *
 * Seeds realistic demo data for the mini-RFQ buyer→seller→commission system.
 *
 * Covers every major workflow state:
 *   A) New RFQ, seller not yet contacted          (seller_response_status = not_requested)
 *   B) Seller response requested, awaiting reply   (seller_response_status = awaiting_response)
 *   C) Seller submitted terms, buyer reviewing     (seller_response_status = submitted)
 *   D) Accepted, handover completed, code issued   (seller_response_status = accepted, discount code generated)
 *   E) Code claimed, invoice pending 15-day debit  (invoice status = pending)
 *   F) Commission charged successfully             (invoice status = charged)
 *   G) Commission disputed by seller               (invoice status = disputed)
 *
 * Usage:
 *   wp eval-file wp-content/plugins/khm-plugin/tests/helpers/rfq_demo_seed.php
 *
 * Safe to re-run — all inserts are guarded by email/login existence checks.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

global $wpdb;

// ─── 2. Resolve the real sponsor ID ───────────────────────────────────────────

// The endpoint resolves sponsor via team_members JSON in wp_khm_sponsors.
// We use the first sponsor record's ID (id=1, "Test Sponsor Co") and ensure
// admin (user 1) or test_sponsor (user 2) is listed as a team member.
global $wpdb;
$real_sponsor = $wpdb->get_row( "SELECT id, name, team_members FROM wp_khm_sponsors ORDER BY id ASC LIMIT 1", ARRAY_A );

if ( ! $real_sponsor ) {
	echo "ERROR: No sponsor record found in wp_khm_sponsors. Please create a sponsor first.\n";
	exit( 1 );
}

$real_sponsor_id = (int) $real_sponsor['id'];
$team_members    = json_decode( (string) ( $real_sponsor['team_members'] ?? '[]' ), true );
if ( ! is_array( $team_members ) ) {
	$team_members = [];
}

// Ensure admin (ID 1) is a team member so the portal works when logged in as admin
$admin_in_team = array_filter( $team_members, fn( $m ) => (int) ( $m['user_id'] ?? 0 ) === 1 );
if ( empty( $admin_in_team ) ) {
	$team_members[] = [ 'user_id' => 1, 'role' => 'admin' ];
	$wpdb->update(
		'wp_khm_sponsors',
		[ 'team_members' => wp_json_encode( $team_members ) ],
		[ 'id' => $real_sponsor_id ],
		[ '%s' ],
		[ '%d' ]
	);
	echo "✓ Added admin (user 1) to sponsor team for \"{$real_sponsor['name']}\"\n";
} else {
	echo "✓ Using existing sponsor: id={$real_sponsor_id} name=\"{$real_sponsor['name']}\"\n";
}

echo "\n=== KHM RFQ Demo Seeder ===\n\n";

// ─── 1. Ensure migrations have run (tables now created with fixes applied) ─────

KHM\Plugin::initialize_connect();
echo "✓ Migrations executed\n";

// ─── 2. Create demo WP users ───────────────────────────────────────────────────

function rfq_seed_user( string $login, string $email, string $display_name, string $role ): int {
	$existing = get_user_by( 'login', $login );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	$id = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 16 ),
		'display_name' => $display_name,
		'role'         => $role,
	] );
	if ( is_wp_error( $id ) ) {
		echo "  ERROR creating user {$login}: " . $id->get_error_message() . "\n";
		return 0;
	}
	return (int) $id;
}

$buyer1_id  = rfq_seed_user( 'demo_buyer_alice',   'alice@buyer.demo',    'Alice Thornton',    'subscriber' );
$buyer2_id  = rfq_seed_user( 'demo_buyer_marcus',  'marcus@buyer.demo',   'Marcus Reid',       'subscriber' );
$buyer3_id  = rfq_seed_user( 'demo_buyer_priya',   'priya@buyer.demo',    'Priya Kapoor',      'subscriber' );
$seller1_id = rfq_seed_user( 'demo_seller_nexus',  'hello@nexusmsp.demo', 'Nexus MSP',         'subscriber' );
$seller2_id = rfq_seed_user( 'demo_seller_crest',  'hello@crestit.demo',  'Crest IT Solutions','subscriber' );

echo "✓ Users: buyers ({$buyer1_id}, {$buyer2_id}, {$buyer3_id}), sellers ({$seller1_id}, {$seller2_id})\n";

// ─── 3. Ensure provider rows exist for sellers ────────────────────────────────

function rfq_seed_provider( int $sponsor_id, string $name, string $slug ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'connect_providers';
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s LIMIT 1", $slug ) );
	if ( $existing ) {
		return (int) $existing;
	}
	$wpdb->insert( $table, [
		'blog_id'     => get_current_blog_id(),
		'sponsor_id'  => $sponsor_id,
		'name'        => $name,
		'slug'        => $slug,
		'description' => "Demo provider: {$name}",
		'status'      => 'active',
		'created_at'  => current_time( 'mysql' ),
		'updated_at'  => current_time( 'mysql' ),
	] );
	return (int) $wpdb->insert_id;
}

$provider1_id = rfq_seed_provider( $seller1_id, 'Nexus MSP', 'nexus-msp-demo' );
$provider2_id = rfq_seed_provider( $seller2_id, 'Crest IT Solutions', 'crest-it-demo' );

echo "✓ Providers: {$provider1_id} (Nexus), {$provider2_id} (Crest)\n";

// ─── 4. Seller payment profile (Nexus MSP has pre-registered) ─────────────────

$profiles_table = $wpdb->prefix . 'connect_seller_payment_profiles';

if ( $profiles_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profiles_table ) ) ) {
	$existing_profile = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM `{$profiles_table}` WHERE seller_id = %d LIMIT 1",
		$seller1_id
	) );

	if ( ! $existing_profile ) {
		$wpdb->insert( $profiles_table, [
			'seller_id'               => $seller1_id,
			'stripe_customer_id'      => 'cus_DEMO' . strtoupper( substr( md5( (string) $seller1_id ), 0, 12 ) ),
			'payment_auth_granted_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
			'spend_limit_monthly'     => 1500.00,
			'spend_used_current_month'=> 220.00,
			'spend_reset_at'          => gmdate( 'Y-m-01 00:00:00' ),
			'card_enabled_fallback'   => 1,
			'created_at'              => gmdate( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
			'updated_at'              => current_time( 'mysql' ),
		] );
		echo "✓ Seller payment profile created for Nexus MSP\n";
	} else {
		echo "✓ Seller payment profile already exists for Nexus MSP\n";
	}
} else {
	echo "  WARN: {$profiles_table} does not exist — run migrations first\n";
}

// ─── 5. Seed opportunities ─────────────────────────────────────────────────────

$opps_table = $wpdb->prefix . 'connect_opportunities';

function rfq_seed_opportunity( array $data ): int {
	global $wpdb;
	$opps_table = $wpdb->prefix . 'connect_opportunities';

	// Idempotency: check by dedupe_key
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM `{$opps_table}` WHERE dedupe_key = %s LIMIT 1",
		$data['dedupe_key']
	) );
	if ( $existing ) {
		// Update key fields in case the seeder was previously run with wrong values
		$wpdb->update(
			$opps_table,
			[
				'sponsor_id'   => $data['sponsor_id'] ?? null,
				'request_type' => $data['request_type'] ?? 'rfq_request',
				'rfq_metadata' => $data['rfq_metadata'] ?? null,
			],
			[ 'id' => (int) $existing ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);
		return (int) $existing;
	}

	$defaults = [
		'blog_id'                     => get_current_blog_id(),
		'actor_email_hash'            => md5( $data['dedupe_key'] . '_hash' ),
		'actor_email_domain'          => 'buyer.demo',
		'company_domain'              => 'buyer.demo',
		'request_type'                => 'rfq_request',
		'internal_stage'              => 'rfq',
		'commercial_tier'             => 'mid',
		'person_score'                => rand( 60, 95 ),
		'opportunity_status'          => 'active',
		'commission_eligible'         => 1,
		'source'                      => 'rfq_portal',
		'buyer_validation_status'     => 'verified',
		'buyer_validation_badge_visible' => 1,
		'rfq_count_active'            => 1,
		'created_at'                  => current_time( 'mysql' ),
		'updated_at'                  => current_time( 'mysql' ),
	];

	$wpdb->insert( $opps_table, array_merge( $defaults, $data ) );
	return (int) $wpdb->insert_id;
}

$now     = current_time( 'mysql' );
$ago35d  = gmdate( 'Y-m-d H:i:s', strtotime( '-35 days' ) );
$ago30d  = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
$ago20d  = gmdate( 'Y-m-d H:i:s', strtotime( '-20 days' ) );
$ago5d   = gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) );
$ago45d  = gmdate( 'Y-m-d H:i:s', strtotime( '-45 days' ) );

$opp_a_id = rfq_seed_opportunity( [
	'dedupe_key'       => 'rfq-demo-alice-a',
	'sponsor_id'       => $real_sponsor_id,
	'provider_id'      => $provider1_id,
	'buyer_account_id' => $buyer1_id,
	'rfq_created_at'   => $ago5d,
	'rfq_metadata'     => wp_json_encode( [
		'segment'           => 'Enterprise IT & Infrastructure',
		'region'            => 'United Kingdom',
		'intent'            => 'Active vendor evaluation — EMM platform',
		'scope'             => 'Unified endpoint management platform for 450 distributed field engineers. Must cover Windows, macOS, iOS and Android, with automated patch management, remote wipe, and compliance reporting against ISO 27001.',
		'timeline'          => 'RFQ issued 28 Apr 2026; vendor demos week of 2 Jun; shortlist decision 20 Jun; contract target 1 Aug 2026.',
		'budget_range'      => '£55K–£90K annual licence + £12K onboarding',
		'deadline'          => '2026-06-05',
		'competing_vendors' => [ 'Jamf Pro', 'Microsoft Intune', 'Kandji' ],
	] ),
] );

$opp_b_id = rfq_seed_opportunity( [
	'dedupe_key'       => 'rfq-demo-marcus-b',
	'sponsor_id'       => $real_sponsor_id,
	'provider_id'      => $provider1_id,
	'buyer_account_id' => $buyer2_id,
	'rfq_created_at'   => $ago20d,
	'rfq_metadata'     => wp_json_encode( [
		'segment'           => 'Mid-market IT Services',
		'region'            => 'United Kingdom',
		'intent'            => 'Active vendor evaluation — ITSM replacement',
		'scope'             => 'Cloud-native ITSM replacement for a 1,200-seat organisation currently on ServiceNow Essentials. Requirements include AI-assisted triage, self-service portal, SLA dashboards, and CMDB integration. GDPR data-residency in UK mandatory.',
		'timeline'          => 'RFQ responses due 20 May 2026; supplier presentations 3–7 Jun; final decision 30 Jun; go-live target Q4 2026.',
		'budget_range'      => '£120K–£200K annual + £35K implementation',
		'deadline'          => '2026-05-20',
		'competing_vendors' => [ 'Freshservice', 'Jira Service Management', 'TOPdesk' ],
	] ),
] );

$opp_c_id = rfq_seed_opportunity( [
	'dedupe_key'       => 'rfq-demo-alice-c',
	'sponsor_id'       => $real_sponsor_id,
	'provider_id'      => $provider2_id,
	'buyer_account_id' => $buyer1_id,
	'rfq_created_at'   => $ago20d,
	'rfq_metadata'     => wp_json_encode( [
		'segment'           => 'Enterprise IT Operations',
		'region'            => 'UK & EU (multi-site)',
		'intent'            => 'Vendor shortlisting — network observability platform',
		'scope'             => 'Network monitoring and observability platform replacing SolarWinds across 12 UK and 3 EU sites. Must support SNMP v3, NetFlow, synthetic monitoring, and integrate with existing PagerDuty alerting. 24/7 NOC handoff reports required.',
		'timeline'          => 'Vendor terms under internal legal review; sign-off expected w/c 11 May 2026; contract award 1 Jun 2026.',
		'budget_range'      => '£40K–£70K annual SaaS + £8K professional services',
		'deadline'          => '2026-05-23',
		'competing_vendors' => [ 'Datadog', 'Auvik', 'PRTG Network Monitor' ],
	] ),
] );

$opp_d_id = rfq_seed_opportunity( [
	'dedupe_key'         => 'rfq-demo-priya-d',
	'sponsor_id'         => $real_sponsor_id,
	'provider_id'        => $provider1_id,
	'buyer_account_id'   => $buyer3_id,
	'rfq_created_at'     => $ago30d,
	'rfq_count_active'   => 0,
	'opportunity_status' => 'closed',
	'rfq_metadata'       => wp_json_encode( [
		'segment'           => 'Utilities & Field Operations',
		'region'            => 'United Kingdom',
		'intent'            => 'Contract awarded — implementation phase',
		'scope'             => 'Field service management platform for 300 engineers across utilities maintenance contracts. Offline-capable mobile app, dynamic scheduling, parts inventory, SLA tracking, and customer portal are mandatory. SAP PM integration required.',
		'timeline'          => 'Contract signed. Kickoff scheduled 15 May 2026; phased rollout Jun–Sep 2026.',
		'budget_range'      => '£95K–£160K annual licence + £25K implementation',
		'deadline'          => '2026-04-30',
		'competing_vendors' => [ 'ServiceMax', 'ClickSoftware', 'IFS Field Service' ],
	] ),
] );

$opp_e_id = rfq_seed_opportunity( [
	'dedupe_key'              => 'rfq-demo-priya-e',
	'sponsor_id'              => $real_sponsor_id,
	'provider_id'             => $provider2_id,
	'buyer_account_id'        => $buyer3_id,
	'rfq_created_at'          => $ago45d,
	'rfq_upsell_sent_at'      => $ago35d,
	'rfq_count_active'        => 0,
	'opportunity_status'      => 'closed',
	'buyer_validation_status' => 'verified',
	'rfq_metadata'            => wp_json_encode( [
		'segment'           => 'Legal & Professional Services',
		'region'            => 'United Kingdom',
		'intent'            => 'Deal closed — commission settlement in progress',
		'scope'             => 'Managed Detection and Response (MDR) service covering 8 legal sector offices. 24/7 SOC with UK-based analysts, SIEM/SOAR integration, monthly threat intelligence briefings, and SLA of 15-minute mean time to detect.',
		'timeline'          => 'Deal closed. Discount code claimed. Commission invoice raised for settlement.',
		'budget_range'      => '£180K–£280K annual managed service',
		'deadline'          => '2026-03-31',
		'competing_vendors' => [ 'CrowdStrike Falcon Complete', 'Arctic Wolf', 'Secureworks Taegis' ],
	] ),
] );

echo "✓ Opportunities: A={$opp_a_id} B={$opp_b_id} C={$opp_c_id} D={$opp_d_id} E={$opp_e_id}\n";

// ─── 6. Seed intro threads ─────────────────────────────────────────────────────

$threads_table = $wpdb->prefix . 'connect_intro_threads';

function rfq_seed_thread( array $data ): int {
	global $wpdb;
	$threads_table = $wpdb->prefix . 'connect_intro_threads';

	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM `{$threads_table}` WHERE opportunity_id = %d LIMIT 1",
		$data['opportunity_id']
	) );
	if ( $existing ) {
		return (int) $existing;
	}

	$defaults = [
		'blog_id'                   => get_current_blog_id(),
		'status'                    => 'open',
		'handover_status'           => 'pending',
		'seller_response_status'    => 'not_requested',
		'seller_payment_status'     => 'pending',
		'commission_settled'        => 0,
		'created_at'                => current_time( 'mysql' ),
		'updated_at'                => current_time( 'mysql' ),
	];

	$wpdb->insert( $threads_table, array_merge( $defaults, $data ) );
	return (int) $wpdb->insert_id;
}

// Scenario A: new RFQ, seller not yet contacted
$thread_a = rfq_seed_thread( [
	'opportunity_id'          => $opp_a_id,
	'provider_id'             => $provider1_id,
	'sponsor_id'              => $real_sponsor_id,
	'buyer_name'              => 'Alice Thornton',
	'buyer_company'           => 'Thornton Retail Group',
	'buyer_email_hash'        => md5( 'alice@buyer.demo' ),
	'buyer_token'             => wp_generate_password( 40, false ),
	'seller_response_status'  => 'not_requested',
	'seller_initial_response' => null,
	'last_message_excerpt'    => 'Looking for an MSP to manage 45 endpoints across 3 sites.',
	'latest_message_at'       => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
	'created_at'              => gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) ),
] );

// Scenario B: seller response requested, awaiting reply
$thread_b = rfq_seed_thread( [
	'opportunity_id'         => $opp_b_id,
	'provider_id'            => $provider1_id,
	'sponsor_id'             => $real_sponsor_id,
	'buyer_name'             => 'Marcus Reid',
	'buyer_company'          => 'Reid Logistics Ltd',
	'buyer_email_hash'       => md5( 'marcus@buyer.demo' ),
	'buyer_token'            => wp_generate_password( 40, false ),
	'seller_response_status' => 'awaiting_response',
	'seller_initial_response'=> json_encode( [ 'requested_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-18 days' ) ) ] ),
	'last_message_excerpt'   => 'We need 24/7 support with SLA under 4 hours.',
	'latest_message_at'      => gmdate( 'Y-m-d H:i:s', strtotime( '-18 days' ) ),
	'created_at'             => gmdate( 'Y-m-d H:i:s', strtotime( '-20 days' ) ),
] );

// Scenario C: seller submitted terms, buyer reviewing (Crest IT, Alice)
$thread_c = rfq_seed_thread( [
	'opportunity_id'             => $opp_c_id,
	'provider_id'                => $provider2_id,
	'sponsor_id'                 => $real_sponsor_id,
	'buyer_name'                 => 'Alice Thornton',
	'buyer_company'              => 'Thornton Retail Group',
	'buyer_email_hash'           => md5( 'alice@buyer.demo' ),
	'buyer_token'                => wp_generate_password( 40, false ),
	'seller_response_status'     => 'submitted',
	'seller_response_submitted_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
	'seller_commission_rate'     => 12,
	'seller_initial_response'    => json_encode( [
		'cover_note'       => 'We specialise in retail MSP. 99.5% SLA, 8-hour onboarding.',
		'onboarding_weeks' => 2,
		'contract_months'  => 12,
		'monthly_fee_gbp'  => 4200,
		'handover_type'    => 'direct_intro',
	] ),
	'last_message_excerpt'       => 'Please see our proposed terms attached.',
	'latest_message_at'          => gmdate( 'Y-m-d H:i:s', strtotime( '-14 days' ) ),
	'created_at'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-20 days' ) ),
] );

// Scenario D: accepted, handover done, discount code issued (Nexus, Priya)
$demo_code_d = 'NEXUSDEMO001';
$thread_d = rfq_seed_thread( [
	'opportunity_id'             => $opp_d_id,
	'provider_id'                => $provider1_id,
	'sponsor_id'                 => $real_sponsor_id,
	'buyer_name'                 => 'Priya Kapoor',
	'buyer_company'              => 'Kapoor Health Clinics',
	'buyer_email_hash'           => md5( 'priya@buyer.demo' ),
	'buyer_token'                => wp_generate_password( 40, false ),
	'status'                     => 'open',
	'handover_status'            => 'completed',
	'seller_response_status'     => 'accepted',
	'seller_response_submitted_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-28 days' ) ),
	'seller_commission_rate'     => 10,
	'seller_initial_response'    => json_encode( [
		'cover_note'       => 'Happy to support Kapoor Health. Healthcare MSP specialist.',
		'onboarding_weeks' => 1,
		'contract_months'  => 24,
		'monthly_fee_gbp'  => 6800,
		'handover_type'    => 'direct_intro',
	] ),
	'handover_preference'        => 'direct_intro',
	'buyer_discount_code'        => $demo_code_d,
	'last_message_excerpt'       => 'Handover completed — check your email for the discount code.',
	'latest_message_at'          => gmdate( 'Y-m-d H:i:s', strtotime( '-25 days' ) ),
	'created_at'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
] );

// Scenario E: code claimed, invoice pending (Crest, Priya — older deal)
$demo_code_e     = 'CRESTCLOSE02';
$claimed_at_e    = gmdate( 'Y-m-d H:i:s', strtotime( '-12 days' ) );
$auto_debit_e    = gmdate( 'Y-m-d H:i:s', strtotime( '-12 days +15 days' ) );
$thread_e = rfq_seed_thread( [
	'opportunity_id'             => $opp_e_id,
	'provider_id'                => $provider2_id,
	'sponsor_id'                 => $real_sponsor_id,
	'buyer_name'                 => 'Priya Kapoor',
	'buyer_company'              => 'Kapoor Health Clinics',
	'buyer_email_hash'           => md5( 'priya@buyer.demo' ),
	'buyer_token'                => wp_generate_password( 40, false ),
	'status'                     => 'open',
	'handover_status'            => 'completed',
	'seller_response_status'     => 'accepted',
	'seller_response_submitted_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-43 days' ) ),
	'seller_commission_rate'     => 8,
	'handover_preference'        => 'diary_link',
	'buyer_discount_code'        => $demo_code_e,
	'buyer_discount_claimed_at'  => $claimed_at_e,
	'auto_debit_date'            => $auto_debit_e,
	'seller_payment_status'      => 'pending',
	'last_message_excerpt'       => 'Discount code claimed. Commission invoice raised.',
	'latest_message_at'          => $claimed_at_e,
	'created_at'                 => gmdate( 'Y-m-d H:i:s', strtotime( '-45 days' ) ),
] );

echo "✓ Threads: A={$thread_a} B={$thread_b} C={$thread_c} D={$thread_d} E={$thread_e}\n";

// ─── 7. Seed discount codes ────────────────────────────────────────────────────

$dc_table = $wpdb->prefix . 'connect_discount_codes';

if ( $dc_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $dc_table ) ) ) {

	function rfq_seed_discount_code( array $data ): void {
		global $wpdb;
		$dc_table = $wpdb->prefix . 'connect_discount_codes';
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$dc_table}` WHERE code = %s LIMIT 1", $data['code'] ) );
		if ( ! $exists ) {
			$wpdb->insert( $dc_table, $data );
		}
	}

	// Code D: issued but not yet claimed
	rfq_seed_discount_code( [
		'code'       => $demo_code_d,
		'thread_id'  => $thread_d,
		'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-25 days' ) ),
	] );

	// Code E: claimed 12 days ago
	rfq_seed_discount_code( [
		'code'                    => $demo_code_e,
		'thread_id'               => $thread_e,
		'created_at'              => gmdate( 'Y-m-d H:i:s', strtotime( '-40 days' ) ),
		'claimed_by_buyer_at'     => $claimed_at_e,
		'verified_for_commission_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-11 days' ) ),
	] );

	// Code F: from a completed deal — invoice charged (standalone thread reference reuses thread_d for simplicity)
	rfq_seed_discount_code( [
		'code'                    => 'NEXUSCLOSE03',
		'thread_id'               => $thread_d,
		'created_at'              => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) ),
		'claimed_by_buyer_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '-58 days' ) ),
		'verified_for_commission_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-57 days' ) ),
	] );

	// Code G: disputed
	rfq_seed_discount_code( [
		'code'                => 'CRESTDISP04',
		'thread_id'           => $thread_e,
		'created_at'          => gmdate( 'Y-m-d H:i:s', strtotime( '-50 days' ) ),
		'claimed_by_buyer_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-48 days' ) ),
	] );

	echo "✓ Discount codes seeded (NEXUSDEMO001, CRESTCLOSE02, NEXUSCLOSE03, CRESTDISP04)\n";

} else {
	echo "  WARN: {$dc_table} does not exist — run migrations first\n";
}

// ─── 8. Seed commission invoices ───────────────────────────────────────────────

$inv_table = $wpdb->prefix . 'connect_pending_commission_invoices';

function rfq_seed_invoice( array $data ): void {
	global $wpdb;
	$inv_table = $wpdb->prefix . 'connect_pending_commission_invoices';
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT id FROM `{$inv_table}` WHERE thread_id = %d AND contract_ref = %s LIMIT 1",
		$data['thread_id'],
		$data['contract_ref']
	) );
	if ( ! $exists ) {
		$wpdb->insert( $inv_table, $data );
	}
}

// Invoice for scenario E (pending — debit due in 3 days)
rfq_seed_invoice( [
	'thread_id'        => $thread_e,
	'contract_ref'     => 'KHM-E-2026-001',
	'commission_rate'  => 8,
	'commission_amount'=> 544.00,   // £6800/mo × 8% × 1 month
	'auto_debit_date'  => $auto_debit_e,
	'status'           => 'pending',
	'claimed_at'       => $claimed_at_e,
] );

// Invoice for scenario F (charged)
rfq_seed_invoice( [
	'thread_id'              => $thread_d,
	'contract_ref'           => 'KHM-F-2026-002',
	'commission_rate'        => 10,
	'commission_amount'      => 680.00,   // £6800/mo × 10%
	'auto_debit_date'        => gmdate( 'Y-m-d H:i:s', strtotime( '-43 days' ) ),
	'status'                 => 'charged',
	'stripe_charge_id'       => 'ch_DEMO' . strtoupper( substr( md5( 'charge-demo-f' ), 0, 14 ) ),
	'stripe_payment_intent_id' => 'pi_DEMO' . strtoupper( substr( md5( 'pi-demo-f' ), 0, 14 ) ),
	'claimed_at'             => gmdate( 'Y-m-d H:i:s', strtotime( '-58 days' ) ),
	'settled_at'             => gmdate( 'Y-m-d H:i:s', strtotime( '-43 days' ) ),
] );

// Invoice for scenario G (disputed)
rfq_seed_invoice( [
	'thread_id'        => $thread_e,
	'contract_ref'     => 'KHM-G-2026-003',
	'commission_rate'  => 8,
	'commission_amount'=> 544.00,
	'auto_debit_date'  => gmdate( 'Y-m-d H:i:s', strtotime( '-33 days' ) ),
	'status'           => 'disputed',
	'dispute_reason'   => 'Seller claims deal did not complete — buyer cancelled within cooling-off period.',
	'claimed_at'       => gmdate( 'Y-m-d H:i:s', strtotime( '-48 days' ) ),
] );

// Invoice for scenario A — failed charge (older deal)
rfq_seed_invoice( [
	'thread_id'        => $thread_a,
	'contract_ref'     => 'KHM-A-2026-004',
	'commission_rate'  => 12,
	'commission_amount'=> 504.00,
	'auto_debit_date'  => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
	'status'           => 'failed',
	'claimed_at'       => gmdate( 'Y-m-d H:i:s', strtotime( '-17 days' ) ),
] );

echo "✓ Commission invoices seeded (pending, charged, disputed, failed)\n";

// ─── 9. Mark buyer2 as pending verification (for admin queue demo) ─────────────

update_user_meta( $buyer2_id, 'khm_buyer_validation_status', 'pending' );
$wpdb->query( $wpdb->prepare(
	"UPDATE `{$opps_table}` SET buyer_validation_status = 'pending' WHERE buyer_account_id = %d",
	$buyer2_id
) );
echo "✓ Marcus Reid marked as pending verification (shows in admin queue)\n";

// ─── 10. Summary ────────────────────────────────────────────────────────────────

echo "\n=== Seed complete ===\n";
echo "Buyers  : Alice ({$buyer1_id}), Marcus ({$buyer2_id}, pending verify), Priya ({$buyer3_id})\n";
echo "Sellers : Nexus MSP ({$seller1_id}, payment registered), Crest IT ({$seller2_id})\n";
echo "Threads : {$thread_a} (not_requested) | {$thread_b} (awaiting_response) | {$thread_c} (submitted) | {$thread_d} (accepted+code) | {$thread_e} (claimed+invoice)\n";
echo "Invoices: pending / charged / disputed / failed — visit WP Admin → Memberships → RFQ Commission Report\n\n";
