<?php
/**
 * Secure public form submission pipeline.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinate security checks, validation, persistence, uploads, and delivery.
 */
final class Submission_Handler {
	/**
	 * Create the submission pipeline.
	 *
	 * @param Form_Repository      $forms Form storage.
	 * @param Entry_Repository     $entries Entry storage.
	 * @param Validator            $validator Submission validator.
	 * @param Notification_Service $notifications Delivery service.
	 */
	public function __construct(
		private Form_Repository $forms,
		private Entry_Repository $entries,
		private Validator $validator,
		private Notification_Service $notifications
	) {}

	/**
	 * Process data from REST or a classic post.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $request Request values.
	 * @param array<string, mixed> $files Uploaded files.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( int $form_id, array $request, array $files = array() ): array|\WP_Error {
		$form = $this->forms->find( $form_id );
		if ( ! $form || 'published' !== $form['status'] ) {
			return new \WP_Error( 'mrnf_form_unavailable', __( 'این فرم در دسترس نیست.', 'mrn-form' ), array( 'status' => 404 ) );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( $request['_mrnf_nonce'] ?? '' ), 'mrnf_submit_' . $form_id ) ) {
			return new \WP_Error( 'mrnf_invalid_nonce', __( 'نشست فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.', 'mrn-form' ), array( 'status' => 403 ) );
		}
		if ( ! empty( $request['_mrnf_company'] ) ) {
			return new \WP_Error( 'mrnf_spam', __( 'ارسال شناسایی نشد.', 'mrn-form' ), array( 'status' => 400 ) );
		}
		$loaded_at = absint( $request['_mrnf_loaded_at'] ?? 0 );
		if ( $loaded_at && time() - $loaded_at < 2 ) {
			return new \WP_Error( 'mrnf_too_fast', __( 'لطفاً پس از تکمیل فرم دوباره تلاش کنید.', 'mrn-form' ), array( 'status' => 429 ) );
		}
		if ( ! $this->within_rate_limit( $form_id ) ) {
			return new \WP_Error( 'mrnf_rate_limit', __( 'تعداد تلاش‌ها بیش از حد مجاز است؛ چند دقیقه دیگر دوباره امتحان کنید.', 'mrn-form' ), array( 'status' => 429 ) );
		}

		$raw    = isset( $request['mrnf'] ) && is_array( $request['mrnf'] ) ? wp_unslash( $request['mrnf'] ) : array();
		$result = $this->validator->validate( $form['fields'], $raw );
		$this->handle_files( $form['fields'], $files, $result['values'], $result['errors'] );
		if ( $result['errors'] ) {
			return new \WP_Error(
				'mrnf_validation',
				$form['settings']['errorMessage'],
				array(
					'status' => 422,
					'fields' => $result['errors'],
				)
			);
		}

		$values   = apply_filters( 'mrnf_submission_values', $result['values'], $form );
		$entry_id = 0;
		if ( ! empty( $form['settings']['storeEntries'] ) ) {
			$entry_id = $this->entries->create( $form_id, $values );
			if ( is_wp_error( $entry_id ) ) {
				return $entry_id;
			}
		}

		do_action( 'mrnf_after_entry_created', $entry_id, $form, $values );
		$email_results = $this->notifications->send( $form, $values, (int) $entry_id );
		do_action( 'mrnf_after_submission', $entry_id, $form, $values, $email_results );

		return array(
			'success'  => true,
			'entryId'  => (int) $entry_id,
			'message'  => $form['settings']['successMessage'],
			'redirect' => $form['settings']['redirectUrl'],
		);
	}

	/**
	 * Determine the client IP without trusting forwarded headers by default.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Apply a ten-minute per-IP limit.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	private function within_rate_limit( int $form_id ): bool {
		$ip    = self::client_ip();
		$key   = 'mrnf_rate_' . md5( $form_id . '|' . $ip );
		$count = (int) get_transient( $key );
		if ( $count >= (int) Settings::get( 'rate_limit', 5 ) ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Validate and move uploads into the media library.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param array<string, mixed>             $files Files.
	 * @param array<string, mixed>             $values Values, updated by reference.
	 * @param array<string, string>            $errors Errors, updated by reference.
	 * @return void
	 */
	private function handle_files( array $fields, array $files, array &$values, array &$errors ): void {
		$pending = array();
		foreach ( $fields as $field ) {
			if ( 'file' !== $field['type'] || ! $this->validator->is_visible( $field, $values ) ) {
				continue;
			}
			$key  = $field['key'];
			$file = $files[ $key ] ?? $files[ 'mrnf_' . $key ] ?? null;
			if ( empty( $file['name'] ) ) {
				if ( ! empty( $field['required'] ) ) {
					/* translators: %s: upload field label. */
					$errors[ $key ] = sprintf( __( 'بارگذاری «%s» الزامی است.', 'mrn-form' ), $field['label'] );
				}
				continue;
			}
			$rules      = (array) $field['validation'];
			$extensions = array_filter( array_map( 'sanitize_key', explode( ',', $rules['extensions'] ?? '' ) ) );
			$extension  = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $extensions, true ) ) {
				$errors[ $key ] = __( 'نوع فایل مجاز نیست.', 'mrn-form' );
				continue;
			}
			if ( (int) $file['size'] > (int) ( $rules['maxFileMB'] ?? 5 ) * MB_IN_BYTES ) {
				/* translators: %d: maximum file size in megabytes. */
				$errors[ $key ] = sprintf( __( 'حجم فایل نباید بیشتر از %d مگابایت باشد.', 'mrn-form' ), (int) $rules['maxFileMB'] );
				continue;
			}
			$pending[] = array(
				'field' => $field,
				'file'  => $file,
			);
		}

		// Never move a file when any field is invalid; this prevents orphaned uploads.
		if ( $errors ) {
			return;
		}

		foreach ( $pending as $upload_item ) {
			$field = $upload_item['field'];
			$file  = $upload_item['file'];
			$key   = $field['key'];
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
			if ( isset( $upload['error'] ) ) {
				$errors[ $key ] = sanitize_text_field( $upload['error'] );
				continue;
			}
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $upload['type'],
					'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
					'post_status'    => 'private',
				),
				$upload['file']
			);
			if ( is_wp_error( $attachment_id ) ) {
				$errors[ $key ] = __( 'ذخیره فایل ناموفق بود.', 'mrn-form' );
				continue;
			}
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			$values[ $key ] = esc_url_raw( $upload['url'] );
		}
	}
}
