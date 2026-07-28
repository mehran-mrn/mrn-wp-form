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
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $values Submitted values.
	 * @return int|\WP_Error
	 */
	public function create( int $form_id, array $values ): int|\WP_Error {
		global $wpdb;
		$ip  = Submission_Handler::client_ip();
		$row = array(
			'form_id'     => $form_id,
			'status'      => 'unread',
			'values_json' => Database::encode( $values ),
			'user_id'     => get_current_user_id(),
			'ip_hash'     => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '',
			'user_agent'  => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
			'referer'     => esc_url_raw( false !== wp_get_referer() ? wp_get_referer() : '' ),
			'created_at'  => Database::now(),
		);
		$ok  = $wpdb->insert( Database::table( 'entries' ), $row, array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
		return false === $ok ? new \WP_Error( 'mrnf_entry_error', $wpdb->last_error ) : (int) $wpdb->insert_id;
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
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		$where   = array( '1=1' );
		$params  = array();
		if ( $args['form_id'] ) {
			$where[]  = 'e.form_id = %d';
			$params[] = absint( $args['form_id'] );
		}
		if ( $args['status'] ) {
			$where[]  = 'e.status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		$params[] = max( 1, min( 5000, absint( $args['limit'] ) ) );
		$params[] = absint( $args['offset'] );
		$sql      = "SELECT e.*, f.title AS form_title FROM {$entries} e LEFT JOIN {$forms} f ON f.id=e.form_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY e.created_at DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
		$table  = Database::table( 'entries' );
		$where  = array( '1=1' );
		$params = array();
		if ( $form_id ) {
			$where[]  = 'form_id = %d';
			$params[] = $form_id;
		}
		if ( $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
		$row['id']      = (int) $row['id'];
		$row['form_id'] = (int) $row['form_id'];
		$row['user_id'] = (int) $row['user_id'];
		$row['values']  = Database::decode( $row['values_json'] );
		unset( $row['values_json'] );
		return $row;
	}
}
