<?php
/**
 * REST API for public submissions and editor data.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Expose public submission and protected form-list routes.
 */
final class REST_API {
	/**
	 * Create the REST controller.
	 *
	 * @param Form_Repository    $forms Form storage.
	 * @param Submission_Handler $submissions Submission pipeline.
	 */
	public function __construct(
		private Form_Repository $forms,
		private Submission_Handler $submissions
	) {}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register(): void {
		register_rest_route(
			'mrn-form/v1',
			'/forms/(?P<id>\d+)/nonce',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'nonce' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			'mrn-form/v1',
			'/forms/(?P<id>\d+)/submit',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'submit' ),
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			'mrn-form/v1',
			'/forms',
			array(
				'methods'             => 'GET',
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'callback'            => array( $this, 'forms' ),
			)
		);
	}

	/**
	 * Issue a fresh, non-cacheable submission nonce.
	 *
	 * Page caches can retain the nonce embedded in a rendered form after its
	 * WordPress nonce tick has expired. Fetching it immediately before submit
	 * keeps cached pages usable without weakening submission verification.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function nonce( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$form = $this->forms->find( absint( $request['id'] ) );
		if ( ! $form || 'published' !== $form['status'] ) {
			return new \WP_Error( 'mrnf_form_unavailable', __( 'این فرم در دسترس نیست.', 'mrn-form' ), array( 'status' => 404 ) );
		}

		$response = rest_ensure_response(
			array(
				'nonce' => wp_create_nonce( 'mrnf_submit_' . $form['id'] ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}

	/**
	 * Public submission callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = $this->submissions->submit( absint( $request['id'] ), $request->get_params(), $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Submission handler verifies the form nonce.
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * Form options for the block editor.
	 *
	 * @return \WP_REST_Response
	 */
	public function forms(): \WP_REST_Response {
		$forms = array_map(
			static fn( array $form ): array => array(
				'id'     => $form['id'],
				'title'  => $form['title'],
				'status' => $form['status'],
			),
			$this->forms->all( array( 'limit' => 500 ) )
		);
		return rest_ensure_response( $forms );
	}
}
