<?php
/**
 * Supported form fields and their defaults.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Define supported fields and normalize untrusted builder configuration.
 */
final class Field_Registry {
	/**
	 * Field definitions shared by the builder and renderer.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			'text'     => array(
				'label' => __( 'متن کوتاه', 'mrn-form' ),
				'icon'  => 'text',
				'input' => true,
			),
			'email'    => array(
				'label' => __( 'ایمیل', 'mrn-form' ),
				'icon'  => 'email',
				'input' => true,
			),
			'tel'      => array(
				'label' => __( 'شماره تماس', 'mrn-form' ),
				'icon'  => 'phone',
				'input' => true,
			),
			'number'   => array(
				'label' => __( 'عدد', 'mrn-form' ),
				'icon'  => 'calculator',
				'input' => true,
			),
			'textarea' => array(
				'label' => __( 'متن بلند', 'mrn-form' ),
				'icon'  => 'editor-paragraph',
				'input' => true,
			),
			'select'   => array(
				'label'   => __( 'فهرست انتخاب', 'mrn-form' ),
				'icon'    => 'menu-alt3',
				'input'   => true,
				'choices' => true,
			),
			'radio'    => array(
				'label'   => __( 'انتخاب تکی', 'mrn-form' ),
				'icon'    => 'marker',
				'input'   => true,
				'choices' => true,
			),
			'checkbox' => array(
				'label'   => __( 'انتخاب چندتایی', 'mrn-form' ),
				'icon'    => 'yes-alt',
				'input'   => true,
				'choices' => true,
			),
			'date'     => array(
				'label' => __( 'تاریخ', 'mrn-form' ),
				'icon'  => 'calendar-alt',
				'input' => true,
			),
			'file'     => array(
				'label' => __( 'بارگذاری فایل', 'mrn-form' ),
				'icon'  => 'upload',
				'input' => true,
			),
			'consent'  => array(
				'label' => __( 'پذیرش قوانین', 'mrn-form' ),
				'icon'  => 'privacy',
				'input' => true,
			),
			'hidden'   => array(
				'label' => __( 'فیلد پنهان', 'mrn-form' ),
				'icon'  => 'hidden',
				'input' => true,
			),
			'heading'  => array(
				'label' => __( 'عنوان بخش', 'mrn-form' ),
				'icon'  => 'heading',
				'input' => false,
			),
			'html'     => array(
				'label' => __( 'متن و HTML', 'mrn-form' ),
				'icon'  => 'editor-code',
				'input' => false,
			),
		);
	}

	/**
	 * Create a normalized field.
	 *
	 * @param string               $type Field type.
	 * @param array<string, mixed> $input Overrides.
	 * @return array<string, mixed>
	 */
	public static function normalize( string $type, array $input = array() ): array {
		$types = self::all();
		$type  = isset( $types[ $type ] ) ? $type : 'text';
		$key   = sanitize_key( $input['key'] ?? $type . '_' . wp_generate_password( 6, false, false ) );
		$key   = $key ? $key : $type . '_field';

		$field = wp_parse_args(
			$input,
			array(
				'id'          => sanitize_key( $input['id'] ?? 'fld_' . wp_generate_password( 8, false, false ) ),
				'type'        => $type,
				'key'         => $key,
				'label'       => $types[ $type ]['label'],
				'description' => '',
				'placeholder' => '',
				'default'     => '',
				'required'    => false,
				'width'       => '100',
				'choices'     => array(),
				'validation'  => array(),
				'condition'   => array(),
				'content'     => '',
			)
		);

		$field['id']          = sanitize_key( $field['id'] );
		$field['type']        = $type;
		$field['key']         = $key;
		$field['label']       = sanitize_text_field( $field['label'] );
		$field['description'] = sanitize_text_field( $field['description'] );
		$field['placeholder'] = sanitize_text_field( $field['placeholder'] );
		$field['default']     = is_array( $field['default'] ) ? array_map( 'sanitize_text_field', $field['default'] ) : sanitize_text_field( $field['default'] );
		$field['required']    = ! empty( $field['required'] );
		$field['width']       = in_array( (string) $field['width'], array( '25', '33', '50', '66', '75', '100' ), true ) ? (string) $field['width'] : '100';
		$field['content']     = 'html' === $type ? wp_kses_post( $field['content'] ) : sanitize_text_field( $field['content'] );
		$field['choices']     = self::sanitize_choices( (array) $field['choices'] );
		$field['validation']  = self::sanitize_validation( (array) $field['validation'] );
		$field['condition']   = self::sanitize_condition( (array) $field['condition'] );

		return $field;
	}

	/**
	 * Normalize a complete field list and guarantee unique keys.
	 *
	 * @param array<int, mixed> $fields Fields.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize_many( array $fields ): array {
		$output = array();
		$keys   = array();
		foreach ( $fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$field = self::normalize( sanitize_key( $raw['type'] ?? 'text' ), $raw );
			$base  = $field['key'];
			$index = 2;
			while ( isset( $keys[ $field['key'] ] ) ) {
				$field['key'] = $base . '_' . ( $index++ );
			}
			$keys[ $field['key'] ] = true;
			$output[]              = $field;
		}

		$input_keys = array();
		$types      = self::all();
		foreach ( $output as $field ) {
			if ( ! empty( $types[ $field['type'] ]['input'] ) ) {
				$input_keys[ $field['key'] ] = true;
			}
		}
		foreach ( $output as &$field ) {
			$related = $field['validation']['requiredWithout'] ?? '';
			if ( $related && ( $related === $field['key'] || ! isset( $input_keys[ $related ] ) ) ) {
				$field['validation']['requiredWithout'] = '';
			}
		}
		unset( $field );

		return $output;
	}

	/**
	 * Default contact form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function starter_fields(): array {
		return self::normalize_many(
			array(
				array(
					'type'     => 'text',
					'key'      => 'name',
					'label'    => __( 'نام و نام خانوادگی', 'mrn-form' ),
					'required' => true,
					'width'    => '50',
				),
				array(
					'type'     => 'email',
					'key'      => 'email',
					'label'    => __( 'ایمیل', 'mrn-form' ),
					'required' => true,
					'width'    => '50',
				),
				array(
					'type'  => 'tel',
					'key'   => 'phone',
					'label' => __( 'شماره تماس', 'mrn-form' ),
					'width' => '50',
				),
				array(
					'type'    => 'select',
					'key'     => 'subject',
					'label'   => __( 'موضوع', 'mrn-form' ),
					'width'   => '50',
					'choices' => array( __( 'درخواست مشاوره', 'mrn-form' ), __( 'پشتیبانی', 'mrn-form' ), __( 'همکاری', 'mrn-form' ) ),
				),
				array(
					'type'     => 'textarea',
					'key'      => 'message',
					'label'    => __( 'پیام شما', 'mrn-form' ),
					'required' => true,
				),
			)
		);
	}

	/**
	 * Sanitize choice labels.
	 *
	 * @param array<int, mixed> $choices Choices.
	 * @return array<int, string>
	 */
	private static function sanitize_choices( array $choices ): array {
		return array_values(
			array_filter(
				array_map( static fn( $choice ): string => sanitize_text_field( is_array( $choice ) ? ( $choice['label'] ?? '' ) : $choice ), $choices )
			)
		);
	}

	/**
	 * Sanitize validation rules.
	 *
	 * @param array<string, mixed> $rules Rules.
	 * @return array<string, mixed>
	 */
	private static function sanitize_validation( array $rules ): array {
		return array(
			'min'             => isset( $rules['min'] ) && '' !== $rules['min'] ? (float) $rules['min'] : '',
			'max'             => isset( $rules['max'] ) && '' !== $rules['max'] ? (float) $rules['max'] : '',
			'minLength'       => isset( $rules['minLength'] ) && '' !== $rules['minLength'] ? absint( $rules['minLength'] ) : '',
			'maxLength'       => isset( $rules['maxLength'] ) && '' !== $rules['maxLength'] ? absint( $rules['maxLength'] ) : '',
			'pattern'         => sanitize_text_field( $rules['pattern'] ?? '' ),
			'extensions'      => sanitize_text_field( $rules['extensions'] ?? 'jpg,jpeg,png,pdf,doc,docx' ),
			'maxFileMB'       => max( 1, min( 20, absint( $rules['maxFileMB'] ?? 5 ) ) ),
			'requiredWithout' => sanitize_key( $rules['requiredWithout'] ?? '' ),
		);
	}

	/**
	 * Sanitize conditional display configuration.
	 *
	 * @param array<string, mixed> $condition Condition.
	 * @return array<string, mixed>
	 */
	private static function sanitize_condition( array $condition ): array {
		$operators = array( 'equals', 'not_equals', 'contains', 'not_empty', 'empty' );
		return array(
			'enabled'  => ! empty( $condition['enabled'] ),
			'field'    => sanitize_key( $condition['field'] ?? '' ),
			'operator' => in_array( $condition['operator'] ?? '', $operators, true ) ? $condition['operator'] : 'equals',
			'value'    => sanitize_text_field( $condition['value'] ?? '' ),
		);
	}
}
