<?php
/**
 * Email notification delivery.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Deliver enabled email notifications and record their outcomes.
 */
final class Notification_Service {
	/**
	 * Create the delivery service.
	 *
	 * @param Template_Engine  $templates Merge-tag and email renderer.
	 * @param Entry_Repository $entries Delivery log storage.
	 */
	public function __construct(
		private Template_Engine $templates,
		private Entry_Repository $entries
	) {}

	/**
	 * Deliver all enabled notifications.
	 *
	 * @param array<string, mixed> $form Form.
	 * @param array<string, mixed> $values Values.
	 * @param int                  $entry_id Entry ID or zero.
	 * @return array<int, array<string, mixed>>
	 */
	public function send( array $form, array $values, int $entry_id ): array {
		$results = array();
		if ( empty( $form['settings']['emailNotifications'] ) ) {
			return $results;
		}
		foreach ( $form['notifications'] as $notification ) {
			if ( empty( $notification['enabled'] ) || ! $this->condition_matches( (array) ( $notification['condition'] ?? array() ), $values ) ) {
				continue;
			}
			$to         = $this->templates->render( $notification['to'], $form, $values, $entry_id, false );
			$subject    = $this->templates->render( $notification['subject'], $form, $values, $entry_id, false );
			$body       = $this->templates->render( $notification['body'], $form, $values, $entry_id, true );
			$reply      = $this->templates->render( $notification['replyTo'], $form, $values, $entry_id, false );
			$recipients = preg_split( '/[\s,;]+/', $to );
			$emails     = array_values( array_filter( array_map( 'sanitize_email', $recipients ? $recipients : array() ), 'is_email' ) );
			if ( ! $emails ) {
				$results[] = array(
					'id'      => $notification['id'],
					'sent'    => false,
					'message' => __( 'گیرنده معتبر نیست.', 'mrn-form' ),
				);
				continue;
			}
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			if ( is_email( $reply ) ) {
				$headers[] = 'Reply-To: ' . sanitize_email( $reply );
			}
			$from_email = Settings::get( 'from_email', get_option( 'admin_email' ) );
			$from_name  = Settings::get( 'from_name', get_bloginfo( 'name' ) );
			if ( is_email( $from_email ) ) {
				$headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>';
			}

			$sent = wp_mail( $emails, $subject, $this->templates->email_document( $subject, $body ), $headers );
			foreach ( $emails as $email ) {
				$this->entries->log_notification( (int) $form['id'], $entry_id, $email, $sent ? 'sent' : 'failed', $sent ? '' : __( 'تابع wp_mail ناموفق بود.', 'mrn-form' ) );
			}
			$results[] = array(
				'id'      => $notification['id'],
				'sent'    => $sent,
				'message' => $sent ? '' : __( 'ارسال ایمیل ناموفق بود.', 'mrn-form' ),
			);
			do_action( 'mrnf_after_notification', $notification, $sent, $form, $values, $entry_id );
		}
		return (array) apply_filters( 'mrnf_notification_results', $results, $form, $values, $entry_id );
	}

	/**
	 * Evaluate optional notification routing.
	 *
	 * @param array<string, mixed> $condition Routing condition.
	 * @param array<string, mixed> $values Submitted values.
	 * @return bool
	 */
	private function condition_matches( array $condition, array $values ): bool {
		if ( empty( $condition['enabled'] ) || empty( $condition['field'] ) ) {
			return true;
		}
		$actual = $values[ $condition['field'] ] ?? '';
		$actual = is_array( $actual ) ? implode( ',', $actual ) : (string) $actual;
		$target = (string) ( $condition['value'] ?? '' );
		return match ( $condition['operator'] ?? 'equals' ) {
			'not_equals' => $actual !== $target,
			'contains'   => str_contains( $actual, $target ),
			'not_empty'  => '' !== trim( $actual ),
			'empty'      => '' === trim( $actual ),
			default      => $actual === $target,
		};
	}
}
