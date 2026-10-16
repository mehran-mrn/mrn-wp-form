<?php
/**
 * Attribution normalization and labels.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function wp_unslash( $value ) {
	return stripslashes( $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( $value ) );
}

function esc_url_raw( $url, $protocols = null ) {
	$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	return in_array( $scheme, (array) $protocols, true ) ? filter_var( $url, FILTER_SANITIZE_URL ) : '';
}

function __( $text ) {
	return $text;
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

require_once dirname( __DIR__ ) . '/includes/class-attribution.php';

use MRN\Form\Attribution;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$attribution = Attribution::from_request(
	array(
		'_mrnf_attribution' => json_encode(
			array(
				'source'       => 'google',
				'medium'       => 'cpc',
				'campaign'     => '<b>Fall lessons</b>',
				'landing_page' => 'https://example.com/register/',
				'referrer'     => 'javascript:alert(1)',
			)
		),
	)
);

$assert( 'google' === $attribution['source'], 'Source should be preserved.' );
$assert( 'Fall lessons' === $attribution['campaign'], 'Campaign should be plain text.' );
$assert( ! isset( $attribution['referrer'] ), 'Non-HTTP referrers should be rejected.' );
$assert( 'تبلیغات گوگل' === Attribution::label( $attribution ), 'Google CPC should use the Google Ads label.' );
$assert( 'Google / جست‌وجوی طبیعی' === Attribution::label( array( 'source' => 'google', 'medium' => 'organic' ) ), 'Organic Google traffic should be identified.' );
$assert( 'نامشخص' === Attribution::label( array() ), 'Missing attribution should not be mistaken for direct traffic.' );
$assert( 'مستقیم' === Attribution::label( array( 'source' => 'direct', 'medium' => '(none)' ) ), 'Explicit direct traffic should be identified.' );
$assert( 'example.org / ارجاعی' === Attribution::label( array( 'source' => 'example.org', 'medium' => 'referral' ) ), 'Referral domains should stay readable.' );

echo "Attribution tests passed.\n";
