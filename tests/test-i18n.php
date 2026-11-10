<?php
/**
 * Standalone translation registration and catalogue tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'MRNF_FILE', ABSPATH . 'mrn-form.php' );

function add_action( $hook, $callback ): void {
	if ( 'init' !== $hook ) {
		throw new RuntimeException( 'Plugin translations should be registered on init.' );
	}
	$callback();
}

function plugin_basename( $file ): string {
	return 'mrn-forms/' . basename( $file );
}

function load_plugin_textdomain( $domain, $deprecated, $path ): void {
	if ( 'mrn-forms' !== $domain || 'mrn-forms/languages' !== $path ) {
		throw new RuntimeException( 'The domain should point to the bundled languages directory.' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-i18n.php';

use MRN\Form\I18n;

I18n::register();

$catalogue = file_get_contents( ABSPATH . 'languages/mrn-forms-fa_IR.mo' );
$header    = unpack( 'Vmagic/Vversion/Vcount/Voriginals/Vtranslations', $catalogue );
if ( 0x950412de !== $header['magic'] || 0 !== $header['version'] ) {
	throw new RuntimeException( 'The bundled file should be a valid gettext MO catalogue.' );
}

$translations = array();
for ( $index = 0; $index < $header['count']; ++$index ) {
	$original   = unpack( 'Vlength/Voffset', $catalogue, $header['originals'] + 8 * $index );
	$translated = unpack( 'Vlength/Voffset', $catalogue, $header['translations'] + 8 * $index );
	$translations[ substr( $catalogue, $original['offset'], $original['length'] ) ] = substr( $catalogue, $translated['offset'], $translated['length'] );
}

$expected = array(
	'Forms'                         => 'فرم‌ها',
	'The email address is invalid.' => 'نشانی ایمیل معتبر نیست.',
	'New submission from {form_title}' => 'ارسال جدید از {form_title}',
);
foreach ( $expected as $english => $persian ) {
	if ( $persian !== ( $translations[ $english ] ?? null ) ) {
		throw new RuntimeException( 'The Persian catalogue is missing the translation for ' . $english );
	}
}

echo "I18n tests passed.\n";
