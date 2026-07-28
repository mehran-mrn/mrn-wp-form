<?php
/**
 * Global plugin settings.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Read, merge, and sanitize global plugin settings.
 */
final class Settings {
	public const OPTION = 'mrnf_settings';

	/**
	 * Default global settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'brand_name'          => get_bloginfo( 'name' ),
			'from_name'           => get_bloginfo( 'name' ),
			'from_email'          => get_option( 'admin_email' ),
			'admin_email'         => get_option( 'admin_email' ),
			'primary_color'       => '#0b5e62',
			'accent_color'        => '#d9a04d',
			'email_logo'          => '',
			'delete_on_uninstall' => false,
			'rate_limit'          => 5,
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Get a setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $fallback Optional fallback.
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$settings = self::all();
		return $settings[ $key ] ?? $fallback;
	}

	/**
	 * Sanitize settings from the admin.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$output   = array(
			'brand_name'          => sanitize_text_field( $input['brand_name'] ?? $defaults['brand_name'] ),
			'from_name'           => sanitize_text_field( $input['from_name'] ?? $defaults['from_name'] ),
			'from_email'          => sanitize_email( $input['from_email'] ?? $defaults['from_email'] ),
			'admin_email'         => sanitize_email( $input['admin_email'] ?? $defaults['admin_email'] ),
			'primary_color'       => sanitize_hex_color( $input['primary_color'] ?? '' ) ? sanitize_hex_color( $input['primary_color'] ) : $defaults['primary_color'],
			'accent_color'        => sanitize_hex_color( $input['accent_color'] ?? '' ) ? sanitize_hex_color( $input['accent_color'] ) : $defaults['accent_color'],
			'email_logo'          => esc_url_raw( $input['email_logo'] ?? '' ),
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
			'rate_limit'          => max( 1, min( 50, absint( $input['rate_limit'] ?? $defaults['rate_limit'] ) ) ),
		);

		if ( ! is_email( $output['from_email'] ) ) {
			$output['from_email'] = $defaults['from_email'];
		}
		if ( ! is_email( $output['admin_email'] ) ) {
			$output['admin_email'] = $defaults['admin_email'];
		}

		return $output;
	}
}
