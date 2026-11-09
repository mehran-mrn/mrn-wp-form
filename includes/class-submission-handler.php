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
			return new \WP_Error( 'mrnf_form_unavailable', __( 'این فرم در دسترس نیست.', 'mrn-forms' ), array( 'status' => 404 ) );
		}
		$checked = $this->check_request( $form_id, $request );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		$loaded_at = absint( $request['_mrnf_loaded_at'] ?? 0 );
		if ( $loaded_at && time() - $loaded_at < 2 ) {
			return new \WP_Error( 'mrnf_too_fast', __( 'لطفاً پس از تکمیل فرم دوباره تلاش کنید.', 'mrn-forms' ), array( 'status' => 429 ) );
		}
		if ( ! $this->within_rate_limit( $form_id ) ) {
			return new \WP_Error( 'mrnf_rate_limit', __( 'تعداد تلاش‌ها بیش از حد مجاز است؛ چند دقیقه دیگر دوباره امتحان کنید.', 'mrn-forms' ), array( 'status' => 429 ) );
		}

		$mode = Anar_Integration::mode( $form );
		if ( 'off' !== $mode && ! Anar_Integration::available( $form ) ) {
			return Anar_Integration::unavailable();
		}

		$raw     = isset( $request['mrnf'] ) && is_array( $request['mrnf'] ) ? wp_unslash( $request['mrnf'] ) : array();
		$result  = $this->validator->validate( $form['fields'], $raw, $files );
		$pending = $this->validate_files( $form['fields'], $files, $result['values'], $result['errors'] );
		if ( $result['errors'] ) {
			return $this->validation_error( $form, $result['errors'] );
		}

		if ( 'off' !== $mode ) {
			$phone_key = Anar_Integration::phone_key( $form );
			$identity  = \Anar\Login\Integrations\MrnForm::verify( $form, $result['values'][ $phone_key ] ?? '', $request );
			if ( is_wp_error( $identity ) ) {
				return $identity;
			}
		}

		$attachments = $this->upload_files( $pending, $result['values'], $result['errors'] );
		if ( $result['errors'] ) {
			$this->remove_uploads( $attachments );
			return $this->validation_error( $form, $result['errors'] );
		}

		$values = apply_filters( 'mrnf_submission_values', $result['values'], $form );
		if ( isset( $identity ) ) {
			$values[ $phone_key ] = $identity['identity'];
		}
		$attribution = Settings::get( 'collect_attribution', false ) ? Attribution::from_request( $request ) : array();
		$entry_id    = 0;
		if ( ! empty( $form['settings']['storeEntries'] ) ) {
			$entry_id = $this->entries->create( $form_id, $values, $attribution );
			if ( is_wp_error( $entry_id ) ) {
				$this->remove_uploads( $attachments );
				return $entry_id;
			}
		}

		$registration = array();
		if ( 'registration' === $mode ) {
			$registration = \Anar\Login\Integrations\MrnForm::complete_registration( $form, $identity, (int) $entry_id );
			if ( is_wp_error( $registration ) ) {
				$this->entries->delete( $entry_id );
				$this->remove_uploads( $attachments );
				return $registration;
			}
		}

		do_action( 'mrnf_after_entry_created', $entry_id, $form, $values );
		$email_results = $this->notifications->send( $form, $values, (int) $entry_id );
		do_action( 'mrnf_after_submission', $entry_id, $form, $values, $email_results );

		$response = array(
			'success'  => true,
			'entryId'  => (int) $entry_id,
			'message'  => $form['settings']['successMessage'],
			'redirect' => $form['settings']['redirectUrl'],
		);

		/**
		 * Let integrations send one submission to its own next step, such as payment.
		 *
		 * @param array<string, mixed> $response Response for AJAX and classic posts.
		 * @param array<string, mixed> $form     Form definition.
		 * @param int                  $entry_id Stored entry ID, or 0.
		 * @param array<string, mixed> $values   Validated values.
		 */
		$filtered = apply_filters( 'mrnf_submission_response', $response, $form, (int) $entry_id, $values );
		if ( is_array( $filtered ) ) {
			$response['message']  = (string) ( $filtered['message'] ?? $response['message'] );
			$response['redirect'] = esc_url_raw( (string) ( $filtered['redirect'] ?? $response['redirect'] ) );
		}

		return array_replace( $response, $registration );
	}

	/**
	 * Issue an Anar code for the saved phone field on a published form.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $request Request values.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function request_sms( int $form_id, array $request ): array|\WP_Error {
		$form = $this->forms->find( $form_id );
		if ( ! $form || 'published' !== $form['status'] || 'off' === Anar_Integration::mode( $form ) ) {
			return new \WP_Error( 'mrnf_form_unavailable', __( 'این فرم در دسترس نیست.', 'mrn-forms' ), array( 'status' => 404 ) );
		}
		$checked = $this->check_request( $form_id, $request );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		if ( ! Anar_Integration::available( $form ) ) {
			return Anar_Integration::unavailable();
		}
		$key    = Anar_Integration::phone_key( $form );
		$fields = array_filter( $form['fields'], static fn( array $field ): bool => $key === $field['key'] );
		$raw    = is_array( $request['mrnf'] ?? null ) ? $request['mrnf'] : array();
		$result = $this->validator->validate( $fields, $raw );
		if ( $result['errors'] ) {
			return $this->validation_error( $form, $result['errors'] );
		}
		$proof = $request['proof'] ?? '';
		$proof = is_string( $proof ) && strlen( $proof ) < 2048 ? json_decode( $proof, true, 4 ) : null;
		return \Anar\Login\Integrations\MrnForm::send( $form, $result['values'][ $key ] ?? '', is_array( $proof ) ? $proof : array() );
	}

	/**
	 * Check the form nonce and honeypot.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $request Request values.
	 * @return bool|\WP_Error
	 */
	private function check_request( int $form_id, array $request ): bool|\WP_Error {
		$nonce = $request['_mrnf_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'mrnf_submit_' . $form_id ) ) {
			return new \WP_Error( 'mrnf_invalid_nonce', __( 'نشست فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.', 'mrn-forms' ), array( 'status' => 403 ) );
		}
		if ( ! empty( $request['_mrnf_company'] ) ) {
			return new \WP_Error( 'mrnf_spam', __( 'ارسال شناسایی نشد.', 'mrn-forms' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Build the REST error with per-field validation messages.
	 *
	 * @param array<string, mixed>  $form Stored form.
	 * @param array<string, string> $errors Field errors.
	 * @return \WP_Error
	 */
	private function validation_error( array $form, array $errors ): \WP_Error {
		return new \WP_Error(
			'mrnf_validation',
			$form['settings']['errorMessage'],
			array(
				'status' => 422,
				'fields' => $errors,
			)
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
	 * Validate uploads before consuming the verification code.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param array<string, mixed>             $files Files.
	 * @param array<string, mixed>             $values Validated values.
	 * @param array<string, string>            $errors Errors, updated by reference.
	 * @return array
	 */
	private function validate_files( array $fields, array $files, array $values, array &$errors ): array {
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
					$errors[ $key ] = sprintf( __( 'بارگذاری «%s» الزامی است.', 'mrn-forms' ), $field['label'] );
				}
				continue;
			}
			$rules      = (array) $field['validation'];
			$extensions = array_filter( array_map( 'sanitize_key', explode( ',', $rules['extensions'] ?? '' ) ) );
			$extension  = strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $extensions, true ) ) {
				$errors[ $key ] = __( 'نوع فایل مجاز نیست.', 'mrn-forms' );
				continue;
			}
			if ( (int) $file['size'] > (int) ( $rules['maxFileMB'] ?? 5 ) * MB_IN_BYTES ) {
				/* translators: %d: maximum file size in megabytes. */
				$errors[ $key ] = sprintf( __( 'حجم فایل نباید بیشتر از %d مگابایت باشد.', 'mrn-forms' ), (int) $rules['maxFileMB'] );
				continue;
			}
			$pending[] = array(
				'field' => $field,
				'file'  => $file,
			);
		}

		return $pending;
	}

	/**
	 * Store files and return attachment IDs so a failed submission can roll them back.
	 *
	 * @param array<int, array<string, mixed>> $pending Validated uploads.
	 * @param array<string, mixed>             $values Values, updated by reference.
	 * @param array<string, string>            $errors Errors, updated by reference.
	 * @return int[]
	 */
	private function upload_files( array $pending, array &$values, array &$errors ): array {
		$attachments = array();
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
				$upload['file'],
				0,
				true
			);
			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_file( $upload['file'] );
				$errors[ $key ] = __( 'ذخیره فایل ناموفق بود.', 'mrn-forms' );
				continue;
			}
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
			$attachments[]  = $attachment_id;
			$values[ $key ] = esc_url_raw( $upload['url'] );
		}
		return $attachments;
	}

	/**
	 * Clean up media created by a failed submission.
	 *
	 * @param int[] $attachments Newly created attachment IDs.
	 * @return void
	 */
	private function remove_uploads( array $attachments ): void {
		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}
}
