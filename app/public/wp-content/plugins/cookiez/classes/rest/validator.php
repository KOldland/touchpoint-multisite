<?php

namespace Cookiez\Classes\Rest;

use Cookiez\Classes\Basic_Enum;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Validator
 *
 * Fluent constraint builder for use in Route::validate_fields().
 * Each chain call registers a constraint; __invoke runs them in order and
 * returns the first error string, or null when all constraints pass.
 *
 * The instance is itself callable, so it can be stored directly in the
 * validate_fields() map and invoked by Route::validate().
 *
 * Usage:
 *   ( new Validator() )->string( [ 'message' => esc_html__( 'Name is required' ) ] )
 *   ( new Validator() )->number( [ 'min' => 1, 'max' => 100 ] )
 *   ( new Validator() )->string()->nullable()
 *   ( new Validator() )->in( Cookie_Category::get_values() )
 */
final class Validator {

	/** @var array<int, array{type: string, options: array, allowed?: array}> */
	private array $constraints = [];

	/** When true, all checks are skipped for null / empty-string values. */
	private bool $is_nullable = false;

	/**
	 * Mark the field as optional.
	 * Validation is skipped entirely when the value is null or an empty string.
	 */
	public function nullable(): self {
		$this->is_nullable = true;

		return $this;
	}

	/**
	 * Non-empty string constraint.
	 *
	 * Options:
	 *   length   (int)    Minimum character length.
	 *   message  (string) Custom error message.
	 */
	public function string( array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'string',
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Numeric value constraint.
	 *
	 * Options:
	 *   min      (int|float) Minimum allowed value.
	 *   max      (int|float) Maximum allowed value.
	 *   message  (string)    Custom error message.
	 */
	public function number( array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'number',
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Valid URL constraint (validated via esc_url_raw).
	 *
	 * Options:
	 *   message  (string) Custom error message.
	 */
	public function url( array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'url',
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Allowlist constraint. Uses strict (===) comparison.
	 *
	 * @param array  $allowed  The permitted values.
	 * @param array  $options
	 *   message  (string) Custom error message.
	 */
	public function in( array $allowed, array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'in',
			'allowed' => $allowed,
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Array constraint. Each item is validated by the provided inner Validator.
	 *
	 * Usage: ( new Validator() )->array( ( new Validator() )->url() )
	 *
	 * Options:
	 *   message       (string) Custom error message for non-array values.
	 *   item_message  (string) Custom error message for a failing item
	 *                          (overrides the inner validator's own message;
	 *                          `%d` is replaced with the item's index).
	 */
	public function array( Validator $item_validator, array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'array',
			'item_validator' => $item_validator,
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Arbitrary predicate constraint.
	 *
	 * Accepts any callable `fn( $value ): bool` — true means valid, false means
	 * invalid.
	 *
	 * Usage:
	 *   ( new Validator() )->fn( fn( $value ) => Order::has_valid_ean( $value ), [ 'message' => '...' ] )
	 *
	 * @param callable<bool> $predicate Validation function.
	 * @param array $options
	 *   message (string) Custom error message.
 */
	public function fn( callable $predicate, array $options = [] ): self {
		$this->constraints[] = [
			'type' => 'fn',
			'predicate' => $predicate,
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Enum constraint — validates against the values of a Basic_Enum subclass.
	 * Calls get_values() on the provided class at validation time.
	 *
	 * Usage: ->enum( Cookie_Category::class, [ 'message' => esc_html__( 'Invalid category', 'cookiez' ) ] )
	 *
	 * @param string $class   Fully-qualified class name (pass ClassName::class).
	 * @param array  $options
	 *   message  (string) Custom error message.
	 */
	public function enum( string $class, array $options = [] ): self {
		if ( ! is_subclass_of( $class, Basic_Enum::class ) ) {
			throw new InvalidArgumentException( sprintf(
				'Validator::enum() expects a Basic_Enum subclass, "%s" given.',
				esc_html( $class )
			) );
		}

		$this->constraints[] = [
			'type' => 'enum',
			'class' => $class,
			'options' => $options,
		];

		return $this;
	}

	/**
	 * Run all registered constraints against $value.
	 *
	 * @return string|null First error message, or null on success.
	 */
	public function __invoke( $value ): ?string {
		if ( $this->is_nullable && ( null === $value || '' === $value ) ) {
			return null;
		}

		foreach ( $this->constraints as $constraint ) {
			$error = $this->run_constraint( $constraint, $value );

			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	private function run_constraint( array $constraint, $value ): ?string {
		$opts = $constraint['options'];

		switch ( $constraint['type'] ) {
			case 'string':
				if ( ! is_string( $value ) || '' === $value ) {
					return $opts['message'] ?? esc_html__( 'This field is required.', 'cookiez' );
				}

				$min = $opts['length'] ?? null;

				if ( null !== $min && mb_strlen( $value ) < (int) $min ) {
					return $opts['message'] ?? sprintf(
						/* translators: %d: minimum character length */
						esc_html__( 'Minimum length is %d characters.', 'cookiez' ),
						$min
					);
				}

				return null;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return $opts['message'] ?? esc_html__( 'This field must be a number.', 'cookiez' );
				}

				if ( isset( $opts['min'] ) && $value < $opts['min'] ) {
					return $opts['message'] ?? sprintf(
						/* translators: %s: minimum value */
						esc_html__( 'Minimum value is %s.', 'cookiez' ),
						$opts['min']
					);
				}

				if ( isset( $opts['max'] ) && $value > $opts['max'] ) {
					return $opts['message'] ?? sprintf(
						/* translators: %s: maximum value */
						esc_html__( 'Maximum value is %s.', 'cookiez' ),
						$opts['max']
					);
				}

				return null;

			case 'url':
				if ( esc_url_raw( (string) $value ) === '' ) {
					return $opts['message'] ?? esc_html__( 'This field must be a valid URL.', 'cookiez' );
				}

				return null;

			case 'in':
				if ( ! in_array( $value, $constraint['allowed'], true ) ) {
					return $opts['message'] ?? esc_html__( 'Invalid value.', 'cookiez' );
				}
				return null;

			case 'enum':
				$allowed = $constraint['class']::get_values();

				if ( ! in_array( $value, $allowed, true ) ) {
					return $opts['message'] ?? esc_html__( 'Invalid value.', 'cookiez' );
				}

				return null;

			case 'fn':
				if ( ( $constraint['predicate'] )( $value ) === false ) {
					return $opts['message'] ?? esc_html__( 'Invalid value.', 'cookiez' );
				}

				return null;

			case 'array':
				if ( ! is_array( $value ) ) {
					return $opts['message'] ?? esc_html__( 'This field must be an array.', 'cookiez' );
				}

				$item_validator = $constraint['item_validator'];

				foreach ( $value as $index => $item ) {
					$item_error = $item_validator( $item );

					if ( null !== $item_error ) {
						if ( isset( $opts['item_message'] ) ) {
							return sprintf( $opts['item_message'], $index );
						}

						return sprintf(
							/* translators: 1: item index, 2: item-level error message */
							esc_html__( 'Item %1$d: %2$s', 'cookiez' ),
							$index,
							$item_error
						);
					}
				}

				return null;
		}

		return null;
	}
}
