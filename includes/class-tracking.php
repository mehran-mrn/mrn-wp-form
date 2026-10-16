<?php
/**
 * Site-wide lead-interaction tracking.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Load the small first-touch and contact-click tracker on public pages.
 */
final class Tracking {
	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the tracker only after the administrator enables its features.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$attribution = (bool) Settings::get( 'collect_attribution', false );
		$analytics   = (bool) Settings::get( 'analytics_events', false );
		if ( ! $attribution && ! $analytics ) {
			return;
		}
		wp_enqueue_script( 'mrnf-tracking', MRNF_URL . 'assets/js/tracking.js', array(), MRNF_VERSION, true );
		wp_localize_script(
			'mrnf-tracking',
			'mrnfTracking',
			array(
				'collectAttribution' => $attribution,
				'analyticsEvents'    => $analytics,
			)
		);
		wp_script_add_data( 'mrnf-tracking', 'strategy', 'defer' );
	}
}
