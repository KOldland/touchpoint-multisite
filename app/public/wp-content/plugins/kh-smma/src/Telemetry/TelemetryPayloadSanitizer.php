<?php
namespace KH_SMMA\Telemetry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OBS-07: Telemetry payload PII masking and field validation.
 *
 * Responsibilities:
 *  1. Detect and redact PII-bearing fields (email, phone, address, etc.).
 *  2. Strip any fields not in the canonical allow-list.
 *  3. Never throw — all operations are best-effort; telemetry must not block execution.
 *
 * Usage in EventEmitter::emit():
 *   $payload = $this->sanitizer->sanitize( $payload );
 *
 * Privacy contract:
 *  - Audit records receive sanitized payloads only.
 *  - PII fields are replaced with REDACTED_MARKER, not silently dropped, so the
 *    presence of a field violation is visible in audit logs.
 *  - Unknown fields (not in ALLOWED_FIELDS) are stripped without warning to
 *    prevent accidental data leakage from future code additions.
 */
class TelemetryPayloadSanitizer {

	const REDACTED_MARKER = '[REDACTED]';

	/**
	 * Regex patterns matched against field names (case-insensitive).
	 * If a field name matches any pattern it is redacted.
	 */
	const PII_FIELD_PATTERNS = array(
		'/email/i',
		'/phone/i',
		'/address/i',
		'/password/i',
		'/card_number/i',
		'/credit_card/i',
		'/payment_method/i',
		'/\bssn\b/i',
		'/tax_id/i',
		'/secret/i',
		'/ip_address/i',
	);

	/**
	 * Exact field names (case-sensitive) that are treated as PII.
	 * Used for fields where a pattern match would be too broad.
	 */
	const PII_FIELD_NAMES = array(
		'first_name',
		'last_name',
		'full_name',
		'display_name',
		'username',
		'user_name',
		'given_name',
		'surname',
		'api_key',
		'access_token',
		'refresh_token',
		'national_id',
	);

	/**
	 * Canonical allow-list of telemetry field names.
	 *
	 * Fields not in this list are stripped from the sanitized payload.
	 * Extend this list when adding new canonical event fields.
	 */
	const ALLOWED_FIELDS = array(
		// Base envelope
		'event_name',
		'trace_id',
		'timestamp',
		'service',

		// generate.request / generate.response
		'session_id',
		'prompt_hash',
		'variant_count_requested',
		'variant_count_generated',
		'latency_ms',
		'model_version',
		'response_hash',

		// compliance.check
		'variant_id',
		'outcome',
		'rules_matched',
		'violations',
		'passed',

		// variant.edit
		'editor_id',
		'revision_id',
		'deltas',
		'unified_diff',

		// schedule.create / schedule.dispatch
		'schedule_id',
		'sponsor_id',
		'approval_required',
		'channel',
		'adapter',
		'result',

		// membership.signup
		'user_id',
		'tier',
		'payment_status',
		'attribution_id',

		// promotion_attribution
		'utm_source',
		'utm_campaign',
		'confidence_score',

		// alert.triggered
		'alert_name',
		'metric_value',
		'threshold',

		// telemetry.config.updated
		'change_type',

		// OBS-06 debug fields (subject to sampling)
		'asset_hint_details',
		'debug_metadata',

		// schedule / post context
		'post_type',
		'status',
		'rule_ids',
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Sanitize a telemetry payload.
	 *
	 * Redacts PII fields and strips unknown fields.
	 * Never throws — returns a best-effort sanitized array.
	 *
	 * @param array $payload Raw caller-supplied payload.
	 * @return array Sanitized payload safe for audit persistence and dispatch.
	 */
	public function sanitize( array $payload ): array {
		try {
			return $this->do_sanitize( $payload );
		} catch ( \Throwable $e ) {
			// Safety net: if sanitization itself fails, return empty payload.
			// PII safety takes priority over telemetry completeness.
			return array(
				'sanitization_error' => true,
				'error_class'        => get_class( $e ),
			);
		}
	}

	/**
	 * Check whether a field name matches a known PII pattern or name.
	 * Public for unit testing.
	 *
	 * @param string $field Field name to check.
	 * @return bool True if the field contains PII.
	 */
	public function is_pii_field( string $field ): bool {
		// Exact name check first (faster and more precise).
		if ( in_array( $field, self::PII_FIELD_NAMES, true ) ) {
			return true;
		}

		// Pattern check.
		foreach ( self::PII_FIELD_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a field is in the canonical allow-list.
	 * Public for unit testing.
	 *
	 * @param string $field Field name to check.
	 * @return bool True if the field is allowed.
	 */
	public function is_allowed_field( string $field ): bool {
		return in_array( $field, self::ALLOWED_FIELDS, true );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	private function do_sanitize( array $payload ): array {
		$sanitized = array();

		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue; // Numeric keys are not valid telemetry fields.
			}

			if ( $this->is_pii_field( $key ) ) {
				// PII detected: redact value but preserve key so violations are visible.
				$sanitized[ $key ] = self::REDACTED_MARKER;
				continue;
			}

			if ( ! $this->is_allowed_field( $key ) ) {
				// Unknown field: strip silently.
				continue;
			}

			$sanitized[ $key ] = $value;
		}

		return $sanitized;
	}
}
