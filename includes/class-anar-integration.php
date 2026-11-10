<?php
/**
 * Form settings and frontend controls for the optional Anar integration.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

use Anar\Login\Integrations\MrnForm;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the Anar settings for a stored form.
 */
final class Anar_Integration {

	/**
	 * Read the verification mode.
	 *
	 * @param array<string, mixed> $form Stored form.
	 * @return string
	 */
	public static function mode( array $form ): string {
		return $form['settings']['anarMode'] ?? 'off';
	}

	/**
	 * Return the configured key only for a required, always visible phone field.
	 *
	 * @param array<string, mixed> $form Stored form.
	 * @return string
	 */
	public static function phone_key( array $form ): string {
		$key = $form['settings']['anarPhoneField'] ?? '';
		foreach ( $form['fields'] as $field ) {
			if ( $key === $field['key'] && 'tel' === $field['type'] && $field['required'] && empty( $field['condition']['enabled'] ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * Check the integration on the server, independently of the rendered form.
	 *
	 * @param array<string, mixed> $form Stored form.
	 * @return bool
	 */
	public static function available( array $form ): bool {
		return '' !== self::phone_key( $form ) && class_exists( MrnForm::class ) && MrnForm::accepts( $form );
	}

	/**
	 * Build the error shown when a form requires an unavailable service.
	 *
	 * @return \WP_Error
	 */
	public static function unavailable(): \WP_Error {
		return new \WP_Error( 'mrnf_anar_unavailable', __( 'SMS verification is currently unavailable for this form.', 'mrn-forms' ), array( 'status' => 503 ) );
	}

	/**
	 * Render the code step separately from the stored form fields.
	 *
	 * @param string $phone_key Verified phone field.
	 * @return void
	 */
	public static function controls( string $phone_key ): void {
		?>
		<div class="mrnf-sms" data-mrnf-sms="<?php echo esc_attr( $phone_key ); ?>">
			<div class="mrnf-sms__heading">
				<span aria-hidden="true">✓</span>
				<div><strong><?php esc_html_e( 'Verify your mobile number', 'mrn-forms' ); ?></strong><p><?php esc_html_e( 'Enter the code we send to your mobile number to submit the form.', 'mrn-forms' ); ?></p></div>
			</div>
			<div class="mrnf-sms__actions">
				<button type="button" data-mrnf-send-code data-resend-label="<?php esc_attr_e( 'Resend code', 'mrn-forms' ); ?>"><?php esc_html_e( 'Send SMS code', 'mrn-forms' ); ?></button>
				<label hidden data-mrnf-code-field><span><?php esc_html_e( 'SMS verification code', 'mrn-forms' ); ?></span><input type="text" name="_mrnf_sms_code" inputmode="numeric" autocomplete="one-time-code" dir="ltr"></label>
			</div>
			<input type="hidden" name="_mrnf_sms_challenge" value="">
			<p class="mrnf-sms__status" role="status" aria-live="polite"></p>
		</div>
		<noscript><p><?php esc_html_e( 'Enable JavaScript in your browser for SMS verification.', 'mrn-forms' ); ?></p></noscript>
		<?php
	}
}
