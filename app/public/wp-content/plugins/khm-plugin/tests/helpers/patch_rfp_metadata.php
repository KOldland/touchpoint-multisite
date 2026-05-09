<?php
/**
 * One-shot patch: set rfp_metadata on demo opportunities.
 * Run via: wp eval-file wp-content/plugins/khm-plugin/tests/helpers/patch_rfp_metadata.php
 */
global $wpdb;

$patches = [
	'rfp-demo-alice-a' => [
		'segment'           => 'Enterprise IT & Infrastructure',
		'region'            => 'United Kingdom',
		'intent'            => 'Active vendor evaluation — EMM platform',
		'scope'             => 'Unified endpoint management platform for 450 distributed field engineers. Must cover Windows, macOS, iOS and Android, with automated patch management, remote wipe, and compliance reporting against ISO 27001.',
		'timeline'          => 'RFP issued 28 Apr 2026; vendor demos week of 2 Jun; shortlist decision 20 Jun; contract target 1 Aug 2026.',
		'budget_range'      => '£55K–£90K annual licence + £12K onboarding',
		'deadline'          => '2026-06-05',
		'competing_vendors' => [ 'Jamf Pro', 'Microsoft Intune', 'Kandji' ],
	],
	'rfp-demo-marcus-b' => [
		'segment'           => 'Mid-market IT Services',
		'region'            => 'United Kingdom',
		'intent'            => 'Active vendor evaluation — ITSM replacement',
		'scope'             => 'Cloud-native ITSM replacement for a 1,200-seat organisation currently on ServiceNow Essentials. Requirements include AI-assisted triage, self-service portal, SLA dashboards, and CMDB integration. GDPR data-residency in UK mandatory.',
		'timeline'          => 'RFP responses due 20 May 2026; supplier presentations 3–7 Jun; final decision 30 Jun; go-live target Q4 2026.',
		'budget_range'      => '£120K–£200K annual + £35K implementation',
		'deadline'          => '2026-05-20',
		'competing_vendors' => [ 'Freshservice', 'Jira Service Management', 'TOPdesk' ],
	],
	'rfp-demo-alice-c' => [
		'segment'           => 'Enterprise IT Operations',
		'region'            => 'UK & EU (multi-site)',
		'intent'            => 'Vendor shortlisting — network observability platform',
		'scope'             => 'Network monitoring and observability platform replacing SolarWinds across 12 UK and 3 EU sites. Must support SNMP v3, NetFlow, synthetic monitoring, and integrate with existing PagerDuty alerting. 24/7 NOC handoff reports required.',
		'timeline'          => 'Vendor terms under internal legal review; sign-off expected w/c 11 May 2026; contract award 1 Jun 2026.',
		'budget_range'      => '£40K–£70K annual SaaS + £8K professional services',
		'deadline'          => '2026-05-23',
		'competing_vendors' => [ 'Datadog', 'Auvik', 'PRTG Network Monitor' ],
	],
	'rfp-demo-priya-d' => [
		'segment'           => 'Utilities & Field Operations',
		'region'            => 'United Kingdom',
		'intent'            => 'Contract awarded — implementation phase',
		'scope'             => 'Field service management platform for 300 engineers across utilities maintenance contracts. Offline-capable mobile app, dynamic scheduling, parts inventory, SLA tracking, and customer portal are mandatory. SAP PM integration required.',
		'timeline'          => 'Contract signed. Kickoff scheduled 15 May 2026; phased rollout Jun–Sep 2026.',
		'budget_range'      => '£95K–£160K annual licence + £25K implementation',
		'deadline'          => '2026-04-30',
		'competing_vendors' => [ 'ServiceMax', 'ClickSoftware', 'IFS Field Service' ],
	],
	'rfp-demo-priya-e' => [
		'segment'           => 'Legal & Professional Services',
		'region'            => 'United Kingdom',
		'intent'            => 'Deal closed — commission settlement in progress',
		'scope'             => 'Managed Detection and Response (MDR) service covering 8 legal sector offices. 24/7 SOC with UK-based analysts, SIEM/SOAR integration, monthly threat intelligence briefings, and SLA of 15-minute mean time to detect.',
		'timeline'          => 'Deal closed. Discount code claimed. Commission invoice raised for settlement.',
		'budget_range'      => '£180K–£280K annual managed service',
		'deadline'          => '2026-03-31',
		'competing_vendors' => [ 'CrowdStrike Falcon Complete', 'Arctic Wolf', 'Secureworks Taegis' ],
	],
];

foreach ( $patches as $dedupe_key => $meta ) {
	$n = $wpdb->update(
		$wpdb->prefix . 'connect_opportunities',
		[ 'rfp_metadata' => wp_json_encode( $meta ) ],
		[ 'dedupe_key'   => $dedupe_key ],
		[ '%s' ],
		[ '%s' ]
	);
	echo "Updated $dedupe_key: $n row(s)\n";
}
echo "Done.\n";
