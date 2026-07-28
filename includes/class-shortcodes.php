<?php
/**
 * Shortcode integration.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Register the theme-independent form shortcode.
 */
final class Shortcodes {
	/**
	 * Create the shortcode integration.
	 *
	 * @param Renderer $renderer Frontend renderer.
	 */
	public function __construct( private Renderer $renderer ) {}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'mrn_form', array( $this, 'form' ) );
	}

	/**
	 * Render [mrn_form id="1"] or [mrn_form slug="contact"].
	 *
	 * @param array<string, mixed> $atts Attributes.
	 * @return string
	 */
	public function form( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'mrn_form'
		);
		return $this->renderer->render( $atts['id'] ? $atts['id'] : sanitize_title( $atts['slug'] ) );
	}
}
