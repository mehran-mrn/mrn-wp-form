<?php
/**
 * Form persistence.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Store and query form definitions in the plugin-owned forms table.
 */
final class Form_Repository {
	/**
	 * Find one form by ID or slug.
	 *
	 * @param int|string $identifier Form ID or slug.
	 * @return array<string, mixed>|null
	 */
	public function find( int|string $identifier ): ?array {
		global $wpdb;
		$table = Database::table( 'forms' );
		if ( is_numeric( $identifier ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $identifier ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $identifier ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Query forms.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function all( array $args = array() ): array {
		global $wpdb;
		$table  = Database::table( 'forms' );
		$args   = wp_parse_args(
			$args,
			array(
				'status' => '',
				'search' => '',
				'limit'  => 100,
				'offset' => 0,
			)
		);
		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( $args['search'] ) {
			$where[]  = 'title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$sql      = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params[] = max( 1, min( 500, absint( $args['limit'] ) ) );
		$params[] = absint( $args['offset'] );
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( $this, 'hydrate' ), $rows ? $rows : array() );
	}

	/**
	 * Create or update a form.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return int|\WP_Error
	 */
	public function save( array $data ): int|\WP_Error {
		global $wpdb;
		$table = Database::table( 'forms' );
		$id    = absint( $data['id'] ?? 0 );
		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( '' === $title ) {
			return new \WP_Error( 'mrnf_title_required', __( 'عنوان فرم الزامی است.', 'mrn-form' ) );
		}

		$slug_source = $data['slug'] ?? '';
		if ( $id && ! array_key_exists( 'slug', $data ) ) {
			$existing    = $this->find( $id );
			$slug_source = $existing['slug'] ?? '';
		}
		$slug    = sanitize_title( $slug_source ? $slug_source : $title );
		$slug    = $this->unique_slug( $slug ? $slug : 'form', $id );
		$row     = array(
			'title'         => $title,
			'slug'          => $slug,
			'status'        => in_array( $data['status'] ?? '', array( 'published', 'draft', 'archived' ), true ) ? $data['status'] : 'draft',
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
			'fields'        => Database::encode( Field_Registry::normalize_many( (array) ( $data['fields'] ?? array() ) ) ),
			'settings'      => Database::encode( $this->sanitize_form_settings( (array) ( $data['settings'] ?? array() ) ) ),
			'notifications' => Database::encode( $this->sanitize_notifications( (array) ( $data['notifications'] ?? array() ) ) ),
			'user_id'       => get_current_user_id(),
			'updated_at'    => Database::now(),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		if ( $id ) {
			$result = $wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return false === $result ? new \WP_Error( 'mrnf_db_error', $wpdb->last_error ) : $id;
		}

		$row['created_at'] = Database::now();
		$formats[]         = '%s';
		$result            = $wpdb->insert( $table, $row, $formats );
		return false === $result ? new \WP_Error( 'mrnf_db_error', $wpdb->last_error ) : (int) $wpdb->insert_id;
	}

	/**
	 * Duplicate a form as a draft.
	 *
	 * @param int $id Source form.
	 * @return int|\WP_Error
	 */
	public function duplicate( int $id ): int|\WP_Error {
		$form = $this->find( $id );
		if ( ! $form ) {
			return new \WP_Error( 'mrnf_not_found', __( 'فرم پیدا نشد.', 'mrn-form' ) );
		}
		unset( $form['id'], $form['created_at'], $form['updated_at'] );
		$form['title']  = $form['title'] . ' ' . __( '(کپی)', 'mrn-form' );
		$form['slug']   = $form['slug'] . '-copy';
		$form['status'] = 'draft';
		return $this->save( $form );
	}

	/**
	 * Delete a form and its related data.
	 *
	 * @param int $id Form ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$wpdb->delete( Database::table( 'notification_logs' ), array( 'form_id' => $id ), array( '%d' ) );
		$wpdb->delete( Database::table( 'entries' ), array( 'form_id' => $id ), array( '%d' ) );
		return false !== $wpdb->delete( Database::table( 'forms' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Count forms.
	 *
	 * @param string $status Optional status.
	 * @return int
	 */
	public function count( string $status = '' ): int {
		global $wpdb;
		$table = Database::table( 'forms' );
		if ( $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Turn database values into domain values.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['id']            = (int) $row['id'];
		$row['user_id']       = (int) $row['user_id'];
		$row['fields']        = Database::decode( $row['fields'] );
		$row['settings']      = wp_parse_args( Database::decode( $row['settings'] ), self::default_settings() );
		$row['notifications'] = Database::decode( $row['notifications'] );
		return $row;
	}

	/**
	 * Form appearance and behavior defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'submitLabel'        => __( 'ارسال فرم', 'mrn-form' ),
			'successMessage'     => __( 'سپاس! اطلاعات شما با موفقیت دریافت شد.', 'mrn-form' ),
			'errorMessage'       => __( 'لطفاً خطاهای مشخص‌شده را برطرف کنید.', 'mrn-form' ),
			'redirectUrl'        => '',
			'ajax'               => true,
			'showTitle'          => true,
			'showDescription'    => true,
			'labelPosition'      => 'top',
			'direction'          => 'auto',
			'layoutGap'          => '16',
			'borderRadius'       => '12',
			'primaryColor'       => Settings::get( 'primary_color', '#0b5e62' ),
			'accentColor'        => Settings::get( 'accent_color', '#d9a04d' ),
			'backgroundColor'    => '#ffffff',
			'textColor'          => '#173b3d',
			'buttonFullWidth'    => false,
			'customClass'        => '',
			'storeEntries'       => true,
			'emailNotifications' => true,
		);
	}

	/**
	 * Default administrator and submitter notifications.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_notifications(): array {
		return array(
			array(
				'id'        => 'admin',
				'name'      => __( 'اعلان مدیر', 'mrn-form' ),
				'enabled'   => true,
				'to'        => '{admin_email}',
				'subject'   => __( 'ارسال جدید از {form_title}', 'mrn-form' ),
				'body'      => __( 'یک ارسال جدید در فرم «{form_title}» دریافت شد.<br><br>{all_fields}', 'mrn-form' ),
				'replyTo'   => '{field:email}',
				'condition' => array(
					'enabled'  => false,
					'field'    => '',
					'operator' => 'equals',
					'value'    => '',
				),
			),
			array(
				'id'        => 'submitter',
				'name'      => __( 'رسید برای کاربر', 'mrn-form' ),
				'enabled'   => true,
				'to'        => '{field:email}',
				'subject'   => __( 'اطلاعات شما دریافت شد | {site_name}', 'mrn-form' ),
				'body'      => __( 'سلام {field:name}،<br><br>اطلاعات شما با موفقیت دریافت شد. نسخه‌ای از پاسخ‌های شما در ادامه آمده است.<br><br>{all_fields}', 'mrn-form' ),
				'replyTo'   => '{admin_email}',
				'condition' => array(
					'enabled'  => false,
					'field'    => '',
					'operator' => 'equals',
					'value'    => '',
				),
			),
		);
	}

	/**
	 * Sanitize per-form settings.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	private function sanitize_form_settings( array $input ): array {
		$data = wp_parse_args( $input, self::default_settings() );
		return array(
			'submitLabel'        => sanitize_text_field( $data['submitLabel'] ),
			'successMessage'     => sanitize_text_field( $data['successMessage'] ),
			'errorMessage'       => sanitize_text_field( $data['errorMessage'] ),
			'redirectUrl'        => esc_url_raw( $data['redirectUrl'] ),
			'ajax'               => ! empty( $data['ajax'] ),
			'showTitle'          => ! empty( $data['showTitle'] ),
			'showDescription'    => ! empty( $data['showDescription'] ),
			'labelPosition'      => in_array( $data['labelPosition'], array( 'top', 'inline', 'hidden' ), true ) ? $data['labelPosition'] : 'top',
			'direction'          => in_array( $data['direction'], array( 'auto', 'rtl', 'ltr' ), true ) ? $data['direction'] : 'auto',
			'layoutGap'          => max( 0, min( 48, absint( $data['layoutGap'] ) ) ),
			'borderRadius'       => max( 0, min( 40, absint( $data['borderRadius'] ) ) ),
			'primaryColor'       => sanitize_hex_color( $data['primaryColor'] ) ? sanitize_hex_color( $data['primaryColor'] ) : '#0b5e62',
			'accentColor'        => sanitize_hex_color( $data['accentColor'] ) ? sanitize_hex_color( $data['accentColor'] ) : '#d9a04d',
			'backgroundColor'    => sanitize_hex_color( $data['backgroundColor'] ) ? sanitize_hex_color( $data['backgroundColor'] ) : '#ffffff',
			'textColor'          => sanitize_hex_color( $data['textColor'] ) ? sanitize_hex_color( $data['textColor'] ) : '#173b3d',
			'buttonFullWidth'    => ! empty( $data['buttonFullWidth'] ),
			'customClass'        => sanitize_html_class( $data['customClass'] ),
			'storeEntries'       => ! empty( $data['storeEntries'] ),
			'emailNotifications' => ! empty( $data['emailNotifications'] ),
		);
	}

	/**
	 * Sanitize notification definitions.
	 *
	 * @param array<int, mixed> $notifications Notifications.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_notifications( array $notifications ): array {
		$output = array();
		foreach ( $notifications as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$output[] = array(
				'id'        => sanitize_key( $raw['id'] ?? 'notification_' . $index ),
				'name'      => sanitize_text_field( $raw['name'] ?? __( 'اعلان ایمیلی', 'mrn-form' ) ),
				'enabled'   => ! empty( $raw['enabled'] ),
				'to'        => sanitize_text_field( $raw['to'] ?? '' ),
				'subject'   => sanitize_text_field( $raw['subject'] ?? '' ),
				'body'      => wp_kses_post( $raw['body'] ?? '' ),
				'replyTo'   => sanitize_text_field( $raw['replyTo'] ?? '' ),
				'condition' => $this->sanitize_notification_condition( (array) ( $raw['condition'] ?? array() ) ),
			);
		}
		return $output ? $output : self::default_notifications();
	}

	/**
	 * Sanitize one notification routing condition.
	 *
	 * @param array<string, mixed> $condition Raw condition.
	 * @return array<string, mixed>
	 */
	private function sanitize_notification_condition( array $condition ): array {
		$operators = array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' );
		return array(
			'enabled'  => ! empty( $condition['enabled'] ),
			'field'    => sanitize_key( $condition['field'] ?? '' ),
			'operator' => in_array( $condition['operator'] ?? '', $operators, true ) ? $condition['operator'] : 'equals',
			'value'    => sanitize_text_field( $condition['value'] ?? '' ),
		);
	}

	/**
	 * Generate a collision-free slug.
	 *
	 * @param string $slug Candidate.
	 * @param int    $exclude_id Existing form ID.
	 * @return string
	 */
	private function unique_slug( string $slug, int $exclude_id ): string {
		global $wpdb;
		$table     = Database::table( 'forms' );
		$candidate = $slug;
		$index     = 2;
		while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", $candidate, $exclude_id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$candidate = $slug . '-' . ( $index++ );
		}
		return $candidate;
	}
}
