<?php
/**
 * Spreadsheet-safe CSV values.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Keep submitted text from becoming a formula when a CSV is opened.
 */
final class CSV_Export {

	/**
	 * Escape spreadsheet formula prefixes, including leading whitespace.
	 *
	 * CSV quoting alone does not stop Excel or LibreOffice evaluating formulas.
	 * The entry itself is unchanged; only the downloaded cell gains an apostrophe.
	 *
	 * @param mixed $value Scalar cell value.
	 * @return string
	 */
	public static function cell( mixed $value ): string {
		$text = (string) $value;
		if ( preg_match( '/^[\s\x00\x{FEFF}]*[=+@-]|^[\t\r\n]/u', $text ) ) {
			return "'" . $text;
		}
		return $text;
	}
}
