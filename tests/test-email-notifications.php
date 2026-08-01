<?php
/**
 * Standalone email template and delivery tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'ARRAY_A', 'ARRAY_A' );

$sent_mail = array();
$test_is_rtl = true;

function __( string $value ): string { return $value; }
function esc_html__( string $value ): string { return $value; }
function is_rtl(): bool {
	global $test_is_rtl;
	return $test_is_rtl;
}
function get_bloginfo( string $key ): string { return 'MRN Test'; }
function get_option( string $key, mixed $fallback = false ): mixed {
	return 'admin_email' === $key ? 'admin@example.com' : $fallback;
}
function wp_parse_args( array $args, array $defaults ): array { return array_merge( $defaults, $args ); }
function home_url(): string { return 'https://example.com/'; }
function esc_html( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( mixed $value ): string { return esc_html( $value ); }
function esc_url( mixed $value ): string { return (string) $value; }
function wp_kses_post( mixed $value ): string { return strip_tags( (string) $value, '<br><table><tr><th><td><a><div><p><strong><em>' ); }
function sanitize_email( mixed $value ): string { return (string) filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( mixed $value ): bool { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( mixed $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function current_time(): string { return '2026-07-28 12:00:00'; }
function wp_json_encode( mixed $value, int $flags = 0 ): string { return (string) json_encode( $value, $flags ); }
function do_action(): void {}
function apply_filters( string $hook, mixed $value ): mixed { return $value; }
function wp_mail( array $to, string $subject, string $body, array $headers ): bool {
	global $sent_mail;
	$sent_mail[] = compact( 'to', 'subject', 'body', 'headers' );
	return true;
}

$wpdb = new class() {
	public string $prefix = 'wp_';
	public array $inserts = array();
	public function insert( string $table, array $row ): bool {
		$this->inserts[] = array( 'table' => $table, 'row' => $row );
		return true;
	}
};

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-database.php';
require_once dirname( __DIR__ ) . '/includes/class-entry-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-template-engine.php';
require_once dirname( __DIR__ ) . '/includes/class-notification-service.php';

use MRN\Form\Entry_Repository;
use MRN\Form\Notification_Service;
use MRN\Form\Template_Engine;

$form = array(
	'id'       => 7,
	'title'    => 'فرم تماس',
	'settings' => array( 'emailNotifications' => true ),
	'fields'   => array(
		array( 'key' => 'name', 'label' => 'نام' ),
		array( 'key' => 'email', 'label' => 'ایمیل' ),
	),
	'notifications' => array(
		array(
			'id' => 'admin', 'enabled' => true, 'to' => '{admin_email}',
			'subject' => 'ارسال {form_title}', 'body' => '{all_fields}', 'replyTo' => '{field:email}',
		),
		array(
			'id' => 'user', 'enabled' => true, 'to' => '{field:email}',
			'subject' => 'رسید {site_name}', 'body' => 'سلام {field:name}', 'replyTo' => '{admin_email}',
		),
		array(
			'id' => 'sales', 'enabled' => true, 'to' => 'sales@example.com',
			'subject' => 'فروش', 'body' => '{all_fields}', 'replyTo' => '',
			'condition' => array( 'enabled' => true, 'field' => 'department', 'operator' => 'equals', 'value' => 'sales' ),
		),
	),
);
$values  = array( 'name' => 'مهران', 'email' => 'user@example.com' );
$service = new Notification_Service( new Template_Engine(), new Entry_Repository() );
$results = $service->send( $form, $values, 42 );
$errors  = array();

if ( 2 !== count( $results ) || ! $results[0]['sent'] || ! $results[1]['sent'] ) {
	$errors[] = 'Both enabled notifications should be sent.';
}
if ( 2 !== count( $sent_mail ) ) {
	$errors[] = 'wp_mail should be called twice.';
}
if ( ! str_contains( $sent_mail[0]['body'], 'مهران' ) || ! str_contains( $sent_mail[0]['body'], '<table' ) ) {
	$errors[] = 'Admin email should contain merged values and the fields table.';
}
if ( array( 'user@example.com' ) !== $sent_mail[1]['to'] ) {
	$errors[] = 'Submitter merge tag should resolve to the submitted email.';
}
if ( 2 !== count( $wpdb->inserts ) || 'wp_mrnf_notification_logs' !== $wpdb->inserts[0]['table'] ) {
	$errors[] = 'Every delivery should be logged in the plugin table.';
}

$test_is_rtl = false;
$engine      = new Template_Engine();
$ltr_body    = $engine->render( '{all_fields}', $form, $values, 42 );
$ltr_email   = $engine->email_document( 'Receipt', $ltr_body );
if ( ! str_contains( $ltr_email, '<html dir="ltr">' ) || ! str_contains( $ltr_email, '<th align="left"' ) ) {
	$errors[] = 'English email documents should render with LTR direction and alignment.';
}

if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL );
	exit( 1 );
}

echo "Email notification tests passed.\n";
