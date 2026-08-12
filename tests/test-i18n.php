<?php
/**
 * Standalone locale catalogue tests.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );

$test_locale = 'en_US';

function add_filter(): void {}
function determine_locale(): string {
	global $test_locale;
	return $test_locale;
}

require_once dirname( __DIR__ ) . '/includes/class-i18n.php';

use MRN\Form\I18n;

$errors = array();

if ( 'Forms' !== I18n::translate( 'فرم‌ها', 'فرم‌ها', 'mrn-form' ) ) {
	$errors[] = 'English locale should translate administration strings.';
}
if ( 'The email address is invalid.' !== I18n::translate( 'نشانی ایمیل معتبر نیست.', 'نشانی ایمیل معتبر نیست.', 'mrn-form' ) ) {
	$errors[] = 'English locale should translate frontend validation strings.';
}
if ( 'Hello {field:name},<br><br>We received your information successfully. A copy of your responses appears below.<br><br>{all_fields}' !== I18n::translate( '', 'سلام {field:name}،<br><br>اطلاعات شما با موفقیت دریافت شد. نسخه‌ای از پاسخ‌های شما در ادامه آمده است.<br><br>{all_fields}', 'mrn-form' ) ) {
	$errors[] = 'English locale should preserve merge tags and email HTML.';
}

$test_locale = 'fa_IR';
if ( 'فرم‌ها' !== I18n::translate( 'فرم‌ها', 'فرم‌ها', 'mrn-form' ) ) {
	$errors[] = 'Persian locale should preserve the original interface.';
}

if ( $errors ) {
	fwrite( STDERR, implode( PHP_EOL, $errors ) . PHP_EOL );
	exit( 1 );
}

echo "I18n tests passed.\n";
