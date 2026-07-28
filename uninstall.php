<?php
/**
 * Optional full data cleanup.
 *
 * @package MRN_Form
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = (array) get_option( 'mrnf_settings', array() );
if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'notification_logs', 'entries', 'forms' ) as $suffix ) {
	$table = $wpdb->prefix . 'mrnf_' . $suffix;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit uninstall cleanup.
}

delete_option( 'mrnf_settings' );
delete_option( 'mrnf_db_version' );
