<?php
/**
 * Verify administrator opt-in gates before any tracking asset is enqueued.
 *
 * @package MRN_Form
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MRNF_URL', 'https://example.test/wp-content/plugins/mrn-forms/' );
define( 'MRNF_VERSION', '1.4.0' );
$test_settings = array();
$enqueued = array();
$config = array();
function get_bloginfo( $key ) { return 'Example site'; }
function get_option( $key, $default = false ) {
	global $test_settings;
	return 'mrnf_settings' === $key ? $test_settings : $default;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function wp_enqueue_script( ...$args ) { global $enqueued; $enqueued[] = $args; }
function wp_localize_script( $handle, $name, $data ) { global $config; $config = $data; }
function wp_script_add_data( ...$args ) {}
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-tracking.php';
$tracker = new MRN\Form\Tracking();
$tracker->enqueue();
if ( $enqueued ) { throw new RuntimeException( 'Default settings must not load tracking.' ); }
$test_settings = array( 'collect_attribution' => true );
$tracker->enqueue();
if ( 1 !== count( $enqueued ) || ! $config['collectAttribution'] || $config['analyticsEvents'] ) {
	throw new RuntimeException( 'Attribution opt-in must not enable external analytics.' );
}
$test_settings = array( 'analytics_events' => true );
$tracker->enqueue();
if ( $config['collectAttribution'] || ! $config['analyticsEvents'] ) {
	throw new RuntimeException( 'Analytics opt-in must not enable attribution storage.' );
}
echo "Tracking settings tests passed.\n";
