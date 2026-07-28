<?php
/**
 * Small dependency-free class loader.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve plugin classes to files without a runtime Composer dependency.
 */
final class Autoloader {
	/**
	 * Register the loader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load one class in this namespace.
	 *
	 * @param string $class_name Fully-qualified class.
	 * @return void
	 */
	private static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = MRNF_PATH . 'includes/class-' . strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
