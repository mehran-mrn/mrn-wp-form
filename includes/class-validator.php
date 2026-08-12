<?php
/**
 * Submission validation and sanitization.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize input and enforce field and conditional validation rules.
 */
final class Validator {
	/**
	 * Validate submitted values against form fields.
	 *
	 * @param array<int, array<string, mixed>> $fields Form fields.
	 * @param array<string, mixed>             $input Raw values.
	 * @param array<string, mixed>             $files Uploaded files.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public function validate( array $fields, array $input, array $files = array() ): array {
		$values = array();
		$errors = array();

		foreach ( $fields as $field ) {
			if ( ! $this->is_input( $field ) || ! $this->is_visible( $field, $input ) ) {
				continue;
			}
			$key   = $field['key'];
			$value = $input[ $key ] ?? '';
			$value = $this->sanitize( $field, $value );

			if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: field label. */
					__( 'تکمیل فیلد «%s» الزامی است.', 'mrn-form' ),
					$field['label']
				);
				continue;
			}
			if ( $this->is_empty( $value ) ) {
				$values[ $key ] = $value;
				continue;
			}

			$error = $this->validate_value( $field, $value );
			if ( $error ) {
				$errors[ $key ] = $error;
			} else {
				$values[ $key ] = $value;
			}
		}

		$this->validate_required_without( $fields, $input, $files, $errors );

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Require this field only when a related field has no submitted value.
	 *
	 * @param array<int, array<string, mixed>> $fields Form fields.
	 * @param array<string, mixed>             $input Raw values.
	 * @param array<string, mixed>             $files Uploaded files.
	 * @param array<string, string>            $errors Errors, updated by reference.
	 * @return void
	 */
	private function validate_required_without( array $fields, array $input, array $files, array &$errors ): void {
		$field_map = array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['key'] ) ) {
				$field_map[ $field['key'] ] = $field;
			}
		}

		foreach ( $fields as $field ) {
			$rules     = (array) ( $field['validation'] ?? array() );
			$other_key = sanitize_key( $rules['requiredWithout'] ?? '' );
			if ( ! $other_key || ! isset( $field_map[ $other_key ] ) || ! $this->is_visible( $field, $input ) ) {
				continue;
			}

			$other = $field_map[ $other_key ];
			if ( $this->has_submission_value( $field, $input, $files ) || $this->has_submission_value( $other, $input, $files ) ) {
				continue;
			}

			$key = $field['key'];
			if ( ! isset( $errors[ $key ] ) ) {
				$errors[ $key ] = sprintf(
					/* translators: 1: field label, 2: related field label. */
					__( 'حداقل یکی از «%1$s» یا «%2$s» باید تکمیل شود.', 'mrn-form' ),
					$field['label'],
					$other['label']
				);
			}
		}
	}

	/**
	 * Whether a regular input or upload has a submitted value.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $input Raw values.
	 * @param array<string, mixed> $files Uploaded files.
	 * @return bool
	 */
	private function has_submission_value( array $field, array $input, array $files ): bool {
		$key = $field['key'];
		if ( 'file' === ( $field['type'] ?? '' ) ) {
			$file = $files[ $key ] ?? $files[ 'mrnf_' . $key ] ?? null;
			return is_array( $file ) && ! empty( $file['name'] ) && UPLOAD_ERR_NO_FILE !== (int) ( $file['error'] ?? UPLOAD_ERR_OK );
		}

		return ! $this->is_empty( $this->sanitize( $field, $input[ $key ] ?? '' ) );
	}

	/**
	 * Whether a condition makes the field visible.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param array<string, mixed> $input All submitted values.
	 * @return bool
	 */
	public function is_visible( array $field, array $input ): bool {
		$condition = (array) ( $field['condition'] ?? array() );
		if ( empty( $condition['enabled'] ) || empty( $condition['field'] ) ) {
			return true;
		}
		$actual = $input[ $condition['field'] ] ?? '';
		$actual = is_array( $actual ) ? implode( ',', array_map( 'strval', $actual ) ) : (string) $actual;
		$target = (string) ( $condition['value'] ?? '' );
		return match ( $condition['operator'] ?? 'equals' ) {
			'not_equals' => $actual !== $target,
			'contains'   => str_contains( $actual, $target ),
			'not_empty'  => '' !== trim( $actual ),
			'empty'      => '' === trim( $actual ),
			default      => $actual === $target,
		};
	}

	/**
	 * Sanitize based on field semantics.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Raw value.
	 * @return mixed
	 */
	private function sanitize( array $field, mixed $value ): mixed {
		$type = $field['type'];
		if ( 'checkbox' === $type ) {
			return array_values( array_map( 'sanitize_text_field', is_array( $value ) ? $value : array( $value ) ) );
		}
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		$value = wp_unslash( (string) $value );
		return match ( $type ) {
			'email'    => sanitize_email( $value ),
			'textarea' => sanitize_textarea_field( $value ),
			'hidden'   => sanitize_text_field( $value ),
			'number'   => is_numeric( $value ) ? (string) $value : sanitize_text_field( $value ),
			default    => sanitize_text_field( $value ),
		};
	}

	/**
	 * Validate a non-empty field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Sanitized value.
	 * @return string
	 */
	private function validate_value( array $field, mixed $value ): string {
		$type  = $field['type'];
		$rules = (array) ( $field['validation'] ?? array() );
		$text  = is_array( $value ) ? implode( ',', $value ) : (string) $value;

		if ( 'email' === $type && ! is_email( $value ) ) {
			return __( 'نشانی ایمیل معتبر نیست.', 'mrn-form' );
		}
		if ( 'number' === $type && ! is_numeric( $value ) ) {
			return __( 'لطفاً یک عدد معتبر وارد کنید.', 'mrn-form' );
		}
		if ( 'date' === $type && ! $this->valid_date( $text ) ) {
			return __( 'تاریخ واردشده معتبر نیست.', 'mrn-form' );
		}
		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
			$choices = (array) ( $field['choices'] ?? array() );
			foreach ( (array) $value as $item ) {
				if ( ! in_array( $item, $choices, true ) ) {
					return __( 'گزینه انتخاب‌شده معتبر نیست.', 'mrn-form' );
				}
			}
		}
		if ( '' !== (string) ( $rules['minLength'] ?? '' ) && mb_strlen( $text ) < (int) $rules['minLength'] ) {
			/* translators: %d: minimum character count. */
			return sprintf( __( 'حداقل %d نویسه وارد کنید.', 'mrn-form' ), (int) $rules['minLength'] );
		}
		if ( '' !== (string) ( $rules['maxLength'] ?? '' ) && mb_strlen( $text ) > (int) $rules['maxLength'] ) {
			/* translators: %d: maximum character count. */
			return sprintf( __( 'حداکثر %d نویسه مجاز است.', 'mrn-form' ), (int) $rules['maxLength'] );
		}
		if ( 'number' === $type && '' !== (string) ( $rules['min'] ?? '' ) && (float) $value < (float) $rules['min'] ) {
			/* translators: %s: minimum numeric value. */
			return sprintf( __( 'مقدار باید حداقل %s باشد.', 'mrn-form' ), $rules['min'] );
		}
		if ( 'number' === $type && '' !== (string) ( $rules['max'] ?? '' ) && (float) $value > (float) $rules['max'] ) {
			/* translators: %s: maximum numeric value. */
			return sprintf( __( 'مقدار باید حداکثر %s باشد.', 'mrn-form' ), $rules['max'] );
		}
		if ( ! empty( $rules['pattern'] ) ) {
			$pattern = '~' . str_replace( '~', '\~', (string) $rules['pattern'] ) . '~u';
			if ( false === @preg_match( $pattern, $text ) || ! preg_match( $pattern, $text ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- User-configured pattern is validated safely.
				return __( 'مقدار واردشده با الگوی مورد انتظار مطابقت ندارد.', 'mrn-form' );
			}
		}
		return '';
	}

	/**
	 * Check whether a field accepts user input.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return bool
	 */
	private function is_input( array $field ): bool {
		return ! in_array( $field['type'] ?? '', array( 'heading', 'html', 'file' ), true );
	}

	/**
	 * Empty semantics that handle arrays.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function is_empty( mixed $value ): bool {
		return is_array( $value ) ? 0 === count( array_filter( $value, static fn( $item ): bool => '' !== (string) $item ) ) : '' === trim( (string) $value );
	}

	/**
	 * Validate an ISO date.
	 *
	 * @param string $value Date.
	 * @return bool
	 */
	private function valid_date( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}
}
