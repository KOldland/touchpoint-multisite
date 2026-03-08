<?php
namespace KH_SMMA\Telemetry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds the current trace/correlation ID for the active request lifecycle.
 *
 * trace_id is a UUID v4 generated (or accepted from an upstream header) at the
 * workflow entry point and propagated to every telemetry event emitted during
 * that request. Shared across: generate.request → generate.response →
 * compliance.check → variant.edit → schedule.create → schedule.dispatch.
 */
class TraceContext {

	/** @var string|null */
	private static $trace_id = null;

	/**
	 * Initialise the trace context for the current request.
	 *
	 * @param string|null $trace_id Caller-supplied trace ID (e.g. from X-Trace-Id
	 *                              header).  When null a fresh UUID v4 is generated.
	 * @return string The active trace_id.
	 */
	public static function init( ?string $trace_id = null ): string {
		if ( null !== $trace_id && '' !== $trace_id ) {
			self::$trace_id = $trace_id;
		} else {
			self::$trace_id = self::generate_uuid4();
		}
		return self::$trace_id;
	}

	/**
	 * Return the currently active trace_id, or null if none is set.
	 */
	public static function current(): ?string {
		return self::$trace_id;
	}

	/**
	 * Return the current trace_id, generating one on-the-fly if none exists.
	 */
	public static function require_current(): string {
		if ( null === self::$trace_id ) {
			return self::init();
		}
		return self::$trace_id;
	}

	/**
	 * Reset the trace context (call at the end of a request or in test tearDown).
	 */
	public static function reset(): void {
		self::$trace_id = null;
	}

	/**
	 * Generate a UUID v4 string.
	 */
	private static function generate_uuid4(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		// Fallback for CLI/test contexts without WordPress.
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}
