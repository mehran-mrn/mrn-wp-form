<?php
/**
 * Submission persistence and reporting.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Store, query, and manage form entries and delivery logs.
 */
final class Entry_Repository {
	/**
	 * Store an entry.
	 *
	 * @param int                   $form_id Form ID.
	 * @param array<string, mixed>  $values Submitted values.
	 * @param array<string, string> $attribution First-touch traffic attribution.
	 * @return int|\WP_Error
	 */
	public function create( int $form_id, array $values, array $attribution = array() ): int|\WP_Error {
		global $wpdb;
		$ip  = Submission_Handler::client_ip();
		$row = array(
			'form_id'          => $form_id,
			'status'           => 'unread',
			'values_json'      => Database::encode( $values ),
			'user_id'          => get_current_user_id(),
			'ip_hash'          => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
			'user_agent'       => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
			'referer'          => esc_url_raw( false !== wp_get_referer() ? wp_get_referer() : '' ),
			'attribution_json' => Database::encode( $attribution ),
			'created_at'       => Database::now(),
		);
		$ok  = $wpdb->insert( Database::table( 'entries' ), $row, array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ) );
		return false === $ok ? new \WP_Error( 'mrnf_entry_error', __( 'Unable to save your submission. Please try again.', 'mrn-forms' ) ) : (int) $wpdb->insert_id;
	}

	/**
	 * Find an entry.
	 *
	 * @param int $id Entry ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = Database::table( 'entries' );
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Query entries with their form titles.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function all( array $args = array() ): array {
		global $wpdb;
		$entries = Database::table( 'entries' );
		$forms   = Database::table( 'forms' );
		$args    = wp_parse_args(
			$args,
			array(
				'form_id' => 0,
				'status'  => '',
				'limit'   => 50,
				'offset'  => 0,
			)
		);
		$form_id = absint( $args['form_id'] );
		$status  = sanitize_key( $args['status'] );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, f.title AS form_title FROM %i e LEFT JOIN %i f ON f.id=e.form_id WHERE (%d = 0 OR e.form_id = %d) AND (%s = '' OR e.status = %s) ORDER BY e.created_at DESC LIMIT %d OFFSET %d",
				$entries,
				$forms,
				$form_id,
				$form_id,
				$status,
				$status,
				max( 1, min( 5000, absint( $args['limit'] ) ) ),
				absint( $args['offset'] )
			),
			ARRAY_A
		);
		return array_map( array( $this, 'hydrate' ), $rows ? $rows : array() );
	}

	/**
	 * Count entries.
	 *
	 * @param int    $form_id Optional form.
	 * @param string $status Optional status.
	 * @return int
	 */
	public function count( int $form_id = 0, string $status = '' ): int {
		global $wpdb;
		$table = Database::table( 'entries' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE (%d = 0 OR form_id = %d) AND (%s = '' OR status = %s)", $table, $form_id, $form_id, $status, $status ) );
	}

	/**
	 * Change read/spam state.
	 *
	 * @param int    $id Entry ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public function set_status( int $id, string $status ): bool {
		global $wpdb;
		if ( ! in_array( $status, array( 'unread', 'read', 'spam' ), true ) ) {
			return false;
		}
		return false !== $wpdb->update( Database::table( 'entries' ), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Delete an entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Database::table( 'notification_logs' ), array( 'entry_id' => $id ), array( '%d' ) );
		return false !== $wpdb->delete( Database::table( 'entries' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Store a notification attempt.
	 *
	 * @param int    $form_id Form ID.
	 * @param int    $entry_id Entry ID.
	 * @param string $recipient Recipient.
	 * @param string $status Status.
	 * @param string $message Note.
	 * @return void
	 */
	public function log_notification( int $form_id, int $entry_id, string $recipient, string $status, string $message = '' ): void {
		global $wpdb;
		$wpdb->insert(
			Database::table( 'notification_logs' ),
			array(
				'form_id'    => $form_id,
				'entry_id'   => $entry_id,
				'channel'    => 'email',
				'recipient'  => sanitize_email( $recipient ),
				'status'     => sanitize_key( $status ),
				'message'    => sanitize_text_field( $message ),
				'created_at' => Database::now(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Hydrate stored JSON.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['id']          = (int) $row['id'];
		$row['form_id']     = (int) $row['form_id'];
		$row['user_id']     = (int) $row['user_id'];
		$row['values']      = Database::decode( $row['values_json'] );
		$row['attribution'] = Database::decode( $row['attribution_json'] ?? '' );
		unset( $row['values_json'] );
		unset( $row['attribution_json'] );
		return $row;
	}
}
