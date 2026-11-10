<?php
/**
 * Register the bundled plugin translations.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Make translations available through the WordPress locale system.
 */
final class I18n {
	/**
	 * Register the translation loader.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ), 0 );
	}

	/**
	 * Register the local catalogue with the WordPress text domain.
	 *
	 * @return void
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'mrn-forms', false, dirname( plugin_basename( MRNF_FILE ) ) . '/languages' );
	}
}
