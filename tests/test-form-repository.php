<?php
/**
 * Standalone form repository regression tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'ARRAY_A', 'ARRAY_A' );

function __( string $value ): string { return $value; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( mixed $value ): string {
	$value = strtolower( trim( (string) $value ) );
	return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
}
function sanitize_key( mixed $value ): string { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_hex_color( mixed $value ): string|false { return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? (string) $value : false; }
function sanitize_html_class( mixed $value ): string { return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ); }
function esc_url_raw( mixed $value ): string { return (string) $value; }
function wp_kses_post( mixed $value ): string { return (string) $value; }
function wp_parse_args( array $args, array $defaults ): array { return array_merge( $defaults, $args ); }
function wp_json_encode( mixed $value ): string { return (string) json_encode( $value ); }
function get_current_user_id(): int { return 1; }
function current_time(): string { return '2026-08-01 12:00:00'; }
function get_bloginfo(): string { return 'MRN Test'; }
function get_option( string $key, mixed $default = false ): mixed { return $default; }

$wpdb = new class() {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public array $updated = array();
	public array $rows = array(
		7 => array(
			'id' => '7', 'title' => 'Contact Form', 'slug' => 'stable-contact-slug',
			'status' => 'published', 'description' => '', 'fields' => '[]',
			'settings' => '{}', 'notifications' => '[]', 'user_id' => '1',
			'created_at' => '2026-08-01 10:00:00', 'updated_at' => '2026-08-01 10:00:00',
		),
	);

	public function prepare( string $query, mixed ...$args ): array {
		return array( 'query' => $query, 'args' => $args );
	}

	public function get_row( array $prepared ): ?array {
		$id = (int) ( $prepared['args'][0] ?? 0 );
		return $this->rows[ $id ] ?? null;
	}

	public function get_var(): int { return 0; }

	public function update( string $table, array $row, array $where ): int {
		$this->updated = compact( 'table', 'row', 'where' );
		return 1;
	}
};

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-database.php';
require_once dirname( __DIR__ ) . '/includes/class-field-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-form-repository.php';

$repository = new \MRN\Form\Form_Repository();
$result = $repository->save(
	array(
		'id' => 7,
		'title' => 'Renamed Contact Form',
		'status' => 'published',
		'fields' => array(),
		'settings' => array(),
		'notifications' => array(
			array( 'id' => 'admin', 'name' => 'Admin', 'enabled' => true, 'to' => 'admin@example.com' ),
		),
	)
);

$errors = array();
if ( 7 !== $result ) {
	$errors[] = 'Updating an existing form should return its ID.';
}
if ( 'stable-contact-slug' !== ( $wpdb->updated['row']['slug'] ?? '' ) ) {
	$errors[] = 'Editing a form without a slug must preserve its existing stable slug.';
}
if ( 'published' !== ( $wpdb->updated['row']['status'] ?? '' ) ) {
	$errors[] = 'Editing notifications must preserve the published status.';
}

if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL );
	exit( 1 );
}

echo "Form repository tests passed.\n";
