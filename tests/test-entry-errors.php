<?php
/**
 * Database failures must not disclose SQL or infrastructure through public forms.
 *
 * @package MRN_Form
 */

namespace MRN\Form {
	final class Database {
		public static function encode( $values ) { return json_encode( $values ); }
		public static function table( $suffix ) { return 'wp_mrnf_' . $suffix; }
		public static function now() { return '2026-10-10 12:00:00'; }
	}
	final class Submission_Handler {
		public static function client_ip() { return ''; }
	}
}
namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	class WP_Error {
		public function __construct( public $code, public $message ) {}
	}
	function get_current_user_id() { return 0; }
	function sanitize_text_field( $value ) { return $value; }
	function wp_unslash( $value ) { return $value; }
	function wp_get_referer() { return false; }
	function esc_url_raw( $value ) { return $value; }
	function __( $value, $domain ) { return $value; }
	$wpdb = new class() {
		public $last_error = 'SQL error in private_database.wp_mrnf_entries on internal-db.example';
		public function insert( ...$args ) { return false; }
	};
	require_once dirname( __DIR__ ) . '/includes/class-entry-repository.php';
	$result = ( new MRN\Form\Entry_Repository() )->create( 1, array( 'message' => 'Hello' ) );
	if ( ! $result instanceof WP_Error || 'mrnf_entry_error' !== $result->code || str_contains( $result->message, 'private_database' ) || str_contains( $result->message, 'internal-db' ) ) {
		throw new RuntimeException( 'A public database error must be generic.' );
	}
	echo "Entry error disclosure regression passed.\n";
}
