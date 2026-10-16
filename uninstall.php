<?php
/**
 * Optional full data cleanup.
 *
 * @package MRN_Form
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mrnf_settings = (array) get_option( 'mrnf_settings', array() );
if ( empty( $mrnf_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'notification_logs', 'entries', 'forms' ) as $mrnf_suffix ) {
	$mrnf_table = $wpdb->prefix . 'mrnf_' . $mrnf_suffix;
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $mrnf_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit uninstall cleanup of plugin-owned tables.
}

delete_option( 'mrnf_settings' );
delete_option( 'mrnf_db_version' );
