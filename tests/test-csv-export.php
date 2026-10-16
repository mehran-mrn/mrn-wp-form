<?php
/**
 * Regression coverage for spreadsheet formula injection in downloaded entries.
 *
 * @package MRN_Form
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-csv-export.php';

$dangerous = array(
	'=HYPERLINK("https://example.test","Open")',
	'+SUM(1,2)',
	'-1+2',
	'@SUM(1,2)',
	" \t=1+2",
	"\ttext",
	"\r=1+2",
	"\n=1+2",
	"\xEF\xBB\xBF=1+2",
);
foreach ( $dangerous as $value ) {
	if ( "'" . $value !== MRN\Form\CSV_Export::cell( $value ) ) {
		throw new RuntimeException( 'Formula-like text must be inert in the exported spreadsheet.' );
	}
}
foreach ( array( '', 'Hello', 'متن فارسی', 'user@example.test', 'https://example.test/', '123', 123, 'a = b', "'already text" ) as $value ) {
	if ( (string) $value !== MRN\Form\CSV_Export::cell( $value ) ) {
		throw new RuntimeException( 'Ordinary values must remain unchanged.' );
	}
}
$stream = fopen( 'php://temp', 'w+' );
$row    = array_map( array( MRN\Form\CSV_Export::class, 'cell' ), array( '=1+2', 'a,b', 'متن' ) );
fputcsv( $stream, $row, ',', '"', '' );
rewind( $stream );
if ( $row !== fgetcsv( $stream, 0, ',', '"', '' ) ) {
	throw new RuntimeException( 'CSV values must survive quoting and parsing intact.' );
}
fclose( $stream );
echo "CSV export regression tests passed.\n";
