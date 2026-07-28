<?php
/**
 * Standalone validator tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

function __( string $value ): string {
	return $value;
}
function wp_unslash( string $value ): string {
	return stripslashes( $value );
}
function sanitize_text_field( mixed $value ): string {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( mixed $value ): string {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_email( mixed $value ): string {
	return (string) filter_var( $value, FILTER_SANITIZE_EMAIL );
}
function is_email( mixed $value ): bool {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
}

require_once dirname( __DIR__ ) . '/includes/class-validator.php';

use MRN\Form\Validator;

$validator = new Validator();
$failures  = array();
$assert    = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$fields = array(
	array(
		'type' => 'email', 'key' => 'email', 'label' => 'ایمیل', 'required' => true,
		'validation' => array(), 'condition' => array(),
	),
	array(
		'type' => 'number', 'key' => 'age', 'label' => 'سن', 'required' => false,
		'validation' => array( 'min' => 18, 'max' => 100 ), 'condition' => array(),
	),
	array(
		'type' => 'text', 'key' => 'company', 'label' => 'شرکت', 'required' => true,
		'validation' => array(), 'condition' => array( 'enabled' => true, 'field' => 'kind', 'operator' => 'equals', 'value' => 'business' ),
	),
);

$valid = $validator->validate( $fields, array( 'email' => 'hello@example.com', 'age' => '27', 'kind' => 'personal' ) );
$assert( array() === $valid['errors'], 'Valid submission should have no errors.' );
$assert( ! array_key_exists( 'company', $valid['values'] ), 'Hidden conditional field should be omitted.' );

$invalid = $validator->validate( $fields, array( 'email' => 'bad-address', 'age' => '12', 'kind' => 'business', 'company' => '' ) );
$assert( isset( $invalid['errors']['email'] ), 'Invalid email should fail.' );
$assert( isset( $invalid['errors']['age'] ), 'Number below minimum should fail.' );
$assert( isset( $invalid['errors']['company'] ), 'Visible required field should fail.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Validator tests passed.\n";
