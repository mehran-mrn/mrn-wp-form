<?php
/**
 * Database names and low-level helpers.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Centralize custom table names and JSON serialization.
 */
final class Database {
	/**
	 * Table name.
	 *
	 * @param string $name Logical table name.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'mrnf_' . $name;
	}

	/**
	 * UTC timestamp in the WordPress SQL format.
	 *
	 * @return string
	 */
	public static function now(): string {
		return current_time( 'mysql', true );
	}

	/**
	 * Decode a stored JSON object safely.
	 *
	 * @param string|null $json JSON value.
	 * @return array<string|int, mixed>
	 */
	public static function decode( ?string $json ): array {
		if ( ! $json ) {
			return array();
		}
		$value = json_decode( $json, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Encode data for storage.
	 *
	 * @param mixed $value Serializable value.
	 * @return string
	 */
	public static function encode( mixed $value ): string {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
