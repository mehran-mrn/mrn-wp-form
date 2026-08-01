<?php
/**
 * Merge tags for notification templates.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve merge tags and build responsive HTML email documents.
 */
final class Template_Engine {
	/**
	 * Replace merge tags.
	 *
	 * @param string               $template Template text.
	 * @param array<string, mixed> $form Form.
	 * @param array<string, mixed> $values Values.
	 * @param int                  $entry_id Entry ID.
	 * @param bool                 $html Whether HTML output is expected.
	 * @return string
	 */
	public function render( string $template, array $form, array $values, int $entry_id, bool $html = true ): string {
		$common = array(
			'{form_title}'  => $form['title'],
			'{form_id}'     => (string) $form['id'],
			'{entry_id}'    => (string) $entry_id,
			'{site_name}'   => get_bloginfo( 'name' ),
			'{site_url}'    => home_url( '/' ),
			'{admin_email}' => Settings::get( 'admin_email', get_option( 'admin_email' ) ),
		);
		foreach ( $common as $tag => $value ) {
			$template = str_replace( $tag, $html ? esc_html( (string) $value ) : (string) $value, $template );
		}
		foreach ( $values as $key => $value ) {
			$text     = is_array( $value ) ? implode( __( '، ', 'mrn-form' ), $value ) : (string) $value;
			$template = str_replace( '{field:' . $key . '}', $html ? esc_html( $text ) : $text, $template );
		}
		$template = str_replace( '{all_fields}', $html ? $this->fields_table( $form, $values ) : $this->fields_text( $form, $values ), $template );

		return (string) preg_replace( '/\{field:[a-zA-Z0-9_-]+\}/', '', $template );
	}

	/**
	 * Render a polished, responsive email document.
	 *
	 * @param string $subject Subject.
	 * @param string $body Merged body HTML.
	 * @return string
	 */
	public function email_document( string $subject, string $body ): string {
		$primary = Settings::get( 'primary_color', '#0b5e62' );
		$accent  = Settings::get( 'accent_color', '#d9a04d' );
		$brand   = Settings::get( 'brand_name', get_bloginfo( 'name' ) );
		$logo    = Settings::get( 'email_logo', '' );
		$dir     = is_rtl() ? 'rtl' : 'ltr';
		$end     = is_rtl() ? 'left' : 'right';
		$mark    = $logo
			? '<img src="' . esc_url( $logo ) . '" width="48" height="48" alt="' . esc_attr( $brand ) . '" style="display:block;max-width:48px;border-radius:12px">'
			: '<div style="width:48px;height:48px;border-radius:12px;background:' . esc_attr( $accent ) . ';color:' . esc_attr( $primary ) . ';font:800 17px Arial;line-height:48px;text-align:center">MRN</div>';

		return '<!doctype html><html dir="' . esc_attr( $dir ) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>'
			. '<body style="margin:0;background:#f5f1e9;color:#173b3d;font-family:Tahoma,Arial,sans-serif">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f1e9"><tr><td align="center" style="padding:32px 12px">'
			. '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(20,47,48,.08)">'
			. '<tr><td style="padding:24px 28px;background:' . esc_attr( $primary ) . ';color:#fff"><table role="presentation" width="100%"><tr><td>' . $mark . '</td><td align="' . esc_attr( $end ) . '" style="color:#f2cf91;font-size:12px;font-weight:bold">' . esc_html( $brand ) . '</td></tr></table></td></tr>'
			. '<tr><td style="padding:32px 28px"><div style="color:' . esc_attr( $accent ) . ';font-size:12px;font-weight:bold;margin-bottom:7px">MRN FORM</div><h1 style="color:' . esc_attr( $primary ) . ';font-size:22px;line-height:1.5;margin:0 0 20px">' . esc_html( $subject ) . '</h1>'
			. '<div style="font-size:14px;line-height:2;color:#344f50">' . wp_kses_post( $body ) . '</div></td></tr>'
			. '<tr><td style="padding:18px 28px;border-top:1px solid #eee9df;color:#7a8784;font-size:11px">' . esc_html__( 'این پیام به‌صورت خودکار توسط MRN Form ارسال شده است.', 'mrn-form' ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * HTML table for all entered fields.
	 *
	 * @param array<string, mixed> $form Form.
	 * @param array<string, mixed> $values Values.
	 * @return string
	 */
	private function fields_table( array $form, array $values ): string {
		$labels = array();
		foreach ( $form['fields'] as $field ) {
			$labels[ $field['key'] ] = $field['label'];
		}
		$start = is_rtl() ? 'right' : 'left';
		$html  = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e7e3da;border-radius:12px;border-collapse:separate;overflow:hidden">';
		foreach ( $values as $key => $value ) {
			$text  = is_array( $value ) ? implode( __( '، ', 'mrn-form' ), $value ) : (string) $value;
			$html .= '<tr><th align="' . esc_attr( $start ) . '" style="width:34%;background:#faf8f3;border-bottom:1px solid #eee9df;padding:11px 13px;color:#61706d;font-size:12px">' . esc_html( $labels[ $key ] ?? $key ) . '</th>'
				. '<td style="border-bottom:1px solid #eee9df;padding:11px 13px;color:#173b3d;font-size:13px;overflow-wrap:anywhere">' . ( str_starts_with( $text, 'http' ) ? '<a href="' . esc_url( $text ) . '">' . esc_html( $text ) . '</a>' : nl2br( esc_html( $text ) ) ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Plain-text field list.
	 *
	 * @param array<string, mixed> $form Form.
	 * @param array<string, mixed> $values Values.
	 * @return string
	 */
	private function fields_text( array $form, array $values ): string {
		$labels = array_column( $form['fields'], 'label', 'key' );
		$lines  = array();
		foreach ( $values as $key => $value ) {
			$lines[] = ( $labels[ $key ] ?? $key ) . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value );
		}
		return implode( "\n", $lines );
	}
}
