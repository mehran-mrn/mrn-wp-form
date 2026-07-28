<?php
/**
 * Install and upgrade database tables.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Manage plugin activation, deactivation, and schema upgrades.
 */
final class Activator {
	/**
	 * Install plugin storage.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::install();
		update_option( 'mrnf_db_version', MRNF_DB_VERSION );
	}

	/**
	 * No scheduled tasks currently need cleanup.
	 *
	 * @return void
	 */
	public static function deactivate(): void {}

	/**
	 * Upgrade when the code database version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( MRNF_DB_VERSION !== get_option( 'mrnf_db_version' ) ) {
			self::activate();
		}
	}

	/**
	 * Create custom tables through dbDelta.
	 *
	 * @return void
	 */
	private static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$forms   = Database::table( 'forms' );
		$entries = Database::table( 'entries' );
		$logs    = Database::table( 'notification_logs' );

		dbDelta(
			"CREATE TABLE {$forms} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				title varchar(190) NOT NULL,
				slug varchar(190) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'draft',
				description longtext NULL,
				fields longtext NOT NULL,
				settings longtext NOT NULL,
				notifications longtext NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY status (status)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$entries} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'unread',
				values_json longtext NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				ip_hash char(64) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				referer varchar(500) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY form_id (form_id),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				form_id bigint(20) unsigned NOT NULL,
				entry_id bigint(20) unsigned NOT NULL,
				channel varchar(20) NOT NULL DEFAULT 'email',
				recipient varchar(190) NOT NULL,
				status varchar(20) NOT NULL,
				message varchar(500) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY entry_id (entry_id),
				KEY form_id (form_id),
				KEY status (status)
			) {$charset};"
		);

		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}
}
