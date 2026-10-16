<?php
/**
 * Traffic attribution captured with form entries.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize browser-supplied first-touch attribution and provide admin labels.
 */
final class Attribution {
	/**
	 * Extract a safe attribution payload from a submission request.
	 *
	 * Attribution is reporting metadata, not an authentication signal. Every
	 * value remains browser supplied and is therefore normalized before storage.
	 *
	 * @param array<string, mixed> $request Submission request.
	 * @return array<string, string>
	 */
	public static function from_request( array $request ): array {
		$raw = $request['_mrnf_attribution'] ?? array();
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$attribution = array(
			'source'       => self::text( $raw['source'] ?? '', 190 ),
			'medium'       => self::text( $raw['medium'] ?? '', 100 ),
			'campaign'     => self::text( $raw['campaign'] ?? '', 190 ),
			'term'         => self::text( $raw['term'] ?? '', 190 ),
			'content'      => self::text( $raw['content'] ?? '', 190 ),
			'landing_page' => self::url( $raw['landing_page'] ?? '' ),
			'referrer'     => self::url( $raw['referrer'] ?? '' ),
			'captured_at'  => self::text( $raw['captured_at'] ?? '', 40 ),
		);

		return array_filter(
			$attribution,
			static fn ( string $value ): bool => '' !== $value
		);
	}

	/**
	 * Build a short human-readable source label for entry lists.
	 *
	 * @param array<string, mixed> $attribution Stored attribution.
	 * @return string
	 */
	public static function label( array $attribution ): string {
		$source = strtolower( trim( (string) ( $attribution['source'] ?? '' ) ) );
		$medium = strtolower( trim( (string) ( $attribution['medium'] ?? '' ) ) );

		if ( '' === $source ) {
			return __( 'نامشخص', 'mrn-forms' );
		}
		if ( in_array( $source, array( 'direct', '(direct)' ), true ) ) {
			return __( 'مستقیم', 'mrn-forms' );
		}

		$paid_media = array( 'cpc', 'ppc', 'paid', 'paid_search', 'paid-social', 'paid_social' );
		if ( 'google' === $source && in_array( $medium, $paid_media, true ) ) {
			return __( 'تبلیغات گوگل', 'mrn-forms' );
		}

		$source_labels = array(
			'google'     => 'Google',
			'bing'       => 'Bing',
			'yahoo'      => 'Yahoo',
			'duckduckgo' => 'DuckDuckGo',
			'facebook'   => 'Facebook',
			'instagram'  => 'Instagram',
			'linkedin'   => 'LinkedIn',
			'twitter'    => 'X / Twitter',
			'youtube'    => 'YouTube',
		);
		$medium_labels = array(
			'organic'  => __( 'جست‌وجوی طبیعی', 'mrn-forms' ),
			'social'   => __( 'شبکه اجتماعی', 'mrn-forms' ),
			'referral' => __( 'ارجاعی', 'mrn-forms' ),
			'email'    => __( 'ایمیل', 'mrn-forms' ),
		);

		$source_label = $source_labels[ $source ] ?? self::display_source( $source );
		$medium_label = $medium_labels[ $medium ] ?? self::display_source( $medium );

		return '' !== $medium_label ? $source_label . ' / ' . $medium_label : $source_label;
	}

	/**
	 * Sanitize and limit a plain-text property.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $length Maximum bytes.
	 * @return string
	 */
	private static function text( mixed $value, int $length ): string {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return substr( $value, 0, $length );
	}

	/**
	 * Keep only HTTP(S) reporting URLs and cap their stored length.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function url( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$url   = esc_url_raw( (string) $value, array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$url = $parts['scheme'] . '://' . $parts['host'];
		if ( isset( $parts['port'] ) ) {
			$url .= ':' . (int) $parts['port'];
		}
		$url .= $parts['path'] ?? '/';
		return substr( $url, 0, 500 );
	}

	/**
	 * Format an unknown source without changing domains into title case.
	 *
	 * @param string $value Normalized source or medium.
	 * @return string
	 */
	private static function display_source( string $value ): string {
		if ( '' === $value || str_contains( $value, '.' ) ) {
			return $value;
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $value ) );
	}
}
