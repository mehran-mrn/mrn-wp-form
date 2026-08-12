<?php
/**
 * Plugin Name:       MRN Form
 * Plugin URI:        https://github.com/mehran-mrn/mrn-wp-form
 * Description:       فرم‌ساز سبک و حرفه‌ای MRN با مدیریت ارسال‌ها، منطق شرطی و اعلان‌های ایمیلی زیبا.
 * Version:           1.2.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Mehran Marandi
 * Author URI:        https://mehranmarandi.ir
 * Text Domain:       mrn-form
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package MRN_Form
 */

defined( 'ABSPATH' ) || exit;

define( 'MRNF_VERSION', '1.2.0' );
define( 'MRNF_DB_VERSION', '1.0.0' );
define( 'MRNF_FILE', __FILE__ );
define( 'MRNF_PATH', plugin_dir_path( __FILE__ ) );
define( 'MRNF_URL', plugin_dir_url( __FILE__ ) );

require_once MRNF_PATH . 'includes/class-autoloader.php';

\MRN\Form\Autoloader::register();

register_activation_hook( __FILE__, array( \MRN\Form\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MRN\Form\Activator::class, 'deactivate' ) );

/**
 * Access the plugin composition root.
 *
 * @return \MRN\Form\Plugin
 */
function mrn_form_plugin(): \MRN\Form\Plugin {
	return \MRN\Form\Plugin::instance();
}

/**
 * Render a form from a theme template without a page builder.
 *
 * @param int|string $form Form ID or slug.
 * @param array      $args Display overrides.
 * @return void
 */
function mrn_form( int|string $form, array $args = array() ): void {
	echo mrn_form_plugin()->renderer()->render( $form, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its complete output.
}

add_action( 'plugins_loaded', 'mrn_form_plugin', 5 );
