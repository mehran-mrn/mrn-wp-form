<?php
/**
 * Dependency-free dynamic Gutenberg block.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Register the dynamic MRN Form block for the block editor.
 */
final class Blocks {
	/**
	 * Create the block integration.
	 *
	 * @param Renderer        $renderer Frontend renderer.
	 * @param Form_Repository $forms Form storage.
	 */
	public function __construct(
		private Renderer $renderer,
		private Form_Repository $forms
	) {}

	/**
	 * Register the editor script and dynamic block.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register block assets and metadata on init.
	 *
	 * @return void
	 */
	public function register_block(): void {
		wp_register_script(
			'mrnf-block',
			MRNF_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ),
			MRNF_VERSION,
			true
		);
		$options = array_map(
			static fn( array $form ): array => array(
				'value' => (string) $form['id'],
				'label' => $form['title'],
			),
			$this->forms->all( array( 'status' => 'published' ) )
		);
		wp_localize_script( 'mrnf-block', 'mrnfBlock', array( 'forms' => $options ) );
		register_block_type(
			'mrn/form',
			array(
				'api_version'     => 3,
				'editor_script'   => 'mrnf-block',
				'attributes'      => array(
					'formId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
				'render_callback' => fn( array $attributes ): string => $this->renderer->render( absint( $attributes['formId'] ?? 0 ) ),
			)
		);
	}
}
