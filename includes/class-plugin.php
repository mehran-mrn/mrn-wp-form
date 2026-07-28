<?php
/**
 * Plugin composition root.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Compose plugin services and expose the public renderer.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Frontend renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Return the singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Compose and register services.
	 *
	 * @return void
	 */
	private function boot(): void {
		Activator::maybe_upgrade();
		$forms          = new Form_Repository();
		$entries        = new Entry_Repository();
		$this->renderer = new Renderer( $forms );
		$templates      = new Template_Engine();
		$notifications  = new Notification_Service( $templates, $entries );
		$submissions    = new Submission_Handler( $forms, $entries, new Validator(), $notifications );

		( new Shortcodes( $this->renderer ) )->register();
		( new Blocks( $this->renderer, $forms ) )->register();
		$rest = new REST_API( $forms, $submissions );
		add_action( 'rest_api_init', array( $rest, 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_post_mrnf_submit', array( $this, 'classic_submission' ) );
		add_action( 'admin_post_nopriv_mrnf_submit', array( $this, 'classic_submission' ) );

		if ( is_admin() ) {
			( new Admin( $forms, $entries ) )->register();
		}
	}

	/**
	 * Load translation files.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'mrn-form', false, dirname( plugin_basename( MRNF_FILE ) ) . '/languages' );
	}

	/**
	 * Expose the renderer to themes.
	 *
	 * @return Renderer
	 */
	public function renderer(): Renderer {
		return $this->renderer;
	}

	/**
	 * Non-JavaScript form submission fallback.
	 *
	 * @return void
	 */
	public function classic_submission(): void {
		$forms       = new Form_Repository();
		$entries     = new Entry_Repository();
		$submissions = new Submission_Handler( $forms, $entries, new Validator(), new Notification_Service( new Template_Engine(), $entries ) );
		$form_id     = absint( $_POST['form_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Handler verifies a form-specific nonce.
		$result      = $submissions->submit( $form_id, $_POST, $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$referer     = wp_get_referer();
		$redirect    = $referer ? $referer : home_url( '/' );
		$query       = is_wp_error( $result ) ? array( 'mrnf_error' => $result->get_error_message() ) : array( 'mrnf_success' => $result['message'] );
		if ( ! is_wp_error( $result ) && $result['redirect'] ) {
			$redirect = $result['redirect'];
			$query    = array();
		}
		wp_safe_redirect( add_query_arg( $query, $redirect ) );
		exit;
	}
}
