<?php
/**
 * Standalone field normalization tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

function __( string $value ): string { return $value; }
function sanitize_key( mixed $value ): string { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function absint( mixed $value ): int { return abs( (int) $value ); }
function wp_generate_password(): string { return 'abcdef'; }
function wp_parse_args( array $args, array $defaults ): array { return array_merge( $defaults, $args ); }
function wp_kses_post( mixed $value ): string { return strip_tags( (string) $value, '<strong><em><a><p><br>' ); }

require_once dirname( __DIR__ ) . '/includes/class-field-registry.php';

use MRN\Form\Field_Registry;

$failures = array();
$fields   = Field_Registry::normalize_many(
	array(
		array( 'type' => 'email', 'key' => 'contact', 'label' => '<b>Email</b>', 'required' => 1 ),
		array( 'type' => 'text', 'key' => 'contact', 'label' => 'Name' ),
		array( 'type' => 'unknown', 'key' => 'fallback', 'label' => 'Fallback' ),
	)
);

if ( 'Email' !== $fields[0]['label'] ) {
	$failures[] = 'Field labels should be sanitized.';
}
if ( 'contact_2' !== $fields[1]['key'] ) {
	$failures[] = 'Duplicate keys should be made unique.';
}
if ( 'text' !== $fields[2]['type'] ) {
	$failures[] = 'Unknown field types should fall back to text.';
}
if ( ! $fields[0]['required'] ) {
	$failures[] = 'Required should normalize to boolean true.';
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Field registry tests passed.\n";
