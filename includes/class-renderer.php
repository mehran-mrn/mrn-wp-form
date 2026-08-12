<?php
/**
 * Accessible frontend form renderer.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Convert stored form definitions into accessible frontend markup.
 */
final class Renderer {
	/**
	 * Create the renderer.
	 *
	 * @param Form_Repository $forms Form storage.
	 */
	public function __construct( private Form_Repository $forms ) {}

	/**
	 * Render a published form.
	 *
	 * @param int|string           $identifier ID or slug.
	 * @param array<string, mixed> $args Display overrides.
	 * @return string
	 */
	public function render( int|string $identifier, array $args = array() ): string {
		$form = $this->forms->find( $identifier );
		if ( ! $form || ( 'published' !== $form['status'] && ! current_user_can( 'manage_options' ) ) ) {
			return current_user_can( 'manage_options' ) ? '<p>' . esc_html__( 'فرم پیدا نشد یا هنوز منتشر نشده است.', 'mrn-form' ) . '</p>' : '';
		}

		wp_enqueue_style( 'mrnf-frontend', MRNF_URL . 'assets/css/frontend.css', array(), MRNF_VERSION );
		wp_enqueue_script( 'mrnf-frontend', MRNF_URL . 'assets/js/frontend.js', array(), MRNF_VERSION, true );
		wp_localize_script(
			'mrnf-frontend',
			'mrnfFrontend',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'mrn-form/v1/forms/' ) ),
				'processing'      => __( 'در حال ارسال…', 'mrn-form' ),
				'network'         => __( 'ارتباط با سرور برقرار نشد. دوباره تلاش کنید.', 'mrn-form' ),
				'close'           => __( 'بستن', 'mrn-form' ),
				/* translators: 1: field label, 2: related field label. */
				'requiredWithout' => __( 'حداقل یکی از «%1$s» یا «%2$s» باید تکمیل شود.', 'mrn-form' ),
			)
		);

		$settings = wp_parse_args( $args, $form['settings'] );
		$style    = sprintf(
			'--mrnf-primary:%1$s;--mrnf-accent:%2$s;--mrnf-bg:%3$s;--mrnf-text:%4$s;--mrnf-gap:%5$dpx;--mrnf-radius:%6$dpx',
			esc_attr( $settings['primaryColor'] ),
			esc_attr( $settings['accentColor'] ),
			esc_attr( $settings['backgroundColor'] ),
			esc_attr( $settings['textColor'] ),
			absint( $settings['layoutGap'] ),
			absint( $settings['borderRadius'] )
		);
		$classes  = array( 'mrnf-form', 'mrnf-labels--' . sanitize_html_class( $settings['labelPosition'] ) );
		if ( ! empty( $settings['buttonFullWidth'] ) ) {
			$classes[] = 'mrnf-form--wide-button';
		}
		if ( ! empty( $settings['customClass'] ) ) {
			$classes[] = sanitize_html_class( $settings['customClass'] );
		}
		$direction = 'auto' === $settings['direction'] ? ( is_rtl() ? 'rtl' : 'ltr' ) : $settings['direction'];

		ob_start();
		?>
		<div class="mrnf-shell" style="<?php echo esc_attr( $style ); ?>" dir="<?php echo esc_attr( $direction ); ?>">
			<form class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-mrnf-form="<?php echo esc_attr( (string) $form['id'] ); ?>" data-ajax="<?php echo ! empty( $settings['ajax'] ) ? '1' : '0'; ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="mrnf_submit">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form['id'] ); ?>">
				<input type="hidden" name="_mrnf_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mrnf_submit_' . $form['id'] ) ); ?>">
				<input type="hidden" name="_mrnf_loaded_at" value="<?php echo esc_attr( (string) time() ); ?>">
				<label class="mrnf-honeypot" aria-hidden="true"><?php esc_html_e( 'Company', 'mrn-form' ); ?><input type="text" name="_mrnf_company" value="" tabindex="-1" autocomplete="off"></label>
				<?php if ( ! empty( $settings['showTitle'] ) ) : ?>
					<h2 class="mrnf-form__title"><?php echo esc_html( $form['title'] ); ?></h2>
				<?php endif; ?>
				<?php if ( ! empty( $settings['showDescription'] ) && $form['description'] ) : ?>
					<p class="mrnf-form__description"><?php echo esc_html( $form['description'] ); ?></p>
				<?php endif; ?>
				<?php
				$query_notice = sanitize_text_field( wp_unslash( $_GET['mrnf_success'] ?? $_GET['mrnf_error'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only classic submission feedback.
				$query_type   = isset( $_GET['mrnf_error'] ) ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				?>
				<div class="mrnf-form__notice <?php echo $query_notice ? 'is-' . esc_attr( $query_type ) . ( 'success' === $query_type ? ' is-toast' : '' ) : ''; ?>" role="status" aria-live="polite" <?php echo $query_notice ? '' : 'hidden'; ?>><span class="mrnf-form__notice-text"><?php echo esc_html( $query_notice ); ?></span><button class="mrnf-form__notice-close" type="button" aria-label="<?php esc_attr_e( 'بستن', 'mrn-form' ); ?>">&times;</button></div>
				<div class="mrnf-form__grid">
					<?php foreach ( $form['fields'] as $field ) : ?>
						<?php echo $this->field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field method escapes values. ?>
					<?php endforeach; ?>
				</div>
				<div class="mrnf-form__actions">
					<button class="mrnf-submit" type="submit"><span><?php echo esc_html( $settings['submitLabel'] ); ?></span><i aria-hidden="true">←</i></button>
				</div>
			</form>
		</div>
		<?php
		return (string) apply_filters( 'mrnf_rendered_form', ob_get_clean(), $form, $settings );
	}

	/**
	 * Render one field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string
	 */
	private function field( array $field ): string {
		$type             = $field['type'];
		$key              = $field['key'];
		$id               = 'mrnf-' . $field['id'];
		$required         = ! empty( $field['required'] );
		$condition        = wp_json_encode( $field['condition'], JSON_UNESCAPED_UNICODE );
		$validation       = wp_json_encode( $field['validation'], JSON_UNESCAPED_UNICODE );
		$required_without = sanitize_key( $field['validation']['requiredWithout'] ?? '' );
		$classes          = 'mrnf-field mrnf-field--' . sanitize_html_class( $type );

		if ( 'heading' === $type ) {
			return '<div class="' . esc_attr( $classes ) . '" style="--mrnf-width:' . esc_attr( $field['width'] ) . '%"><h3>' . esc_html( $field['label'] ) . '</h3>' . ( $field['content'] ? '<p>' . esc_html( $field['content'] ) . '</p>' : '' ) . '</div>';
		}
		if ( 'html' === $type ) {
			return '<div class="' . esc_attr( $classes ) . '" style="--mrnf-width:' . esc_attr( $field['width'] ) . '%">' . wp_kses_post( $field['content'] ) . '</div>';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" style="--mrnf-width:<?php echo esc_attr( $field['width'] ); ?>%" data-mrnf-field="<?php echo esc_attr( $key ); ?>" data-condition="<?php echo esc_attr( $condition ); ?>" data-validation="<?php echo esc_attr( $validation ); ?>"<?php echo $required_without ? ' data-required-without="' . esc_attr( $required_without ) . '"' : ''; ?>>
			<?php if ( ! in_array( $type, array( 'hidden', 'consent' ), true ) ) : ?>
				<label class="mrnf-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?>
				<?php
				if ( $required || $required_without ) :
					?>
					<b class="mrnf-field__required" aria-label="<?php esc_attr_e( 'الزامی', 'mrn-form' ); ?>"<?php echo $required_without && ! $required ? ' data-mrnf-required-indicator' : ''; ?>>*</b><?php endif; ?></label>
			<?php endif; ?>
			<?php $this->control( $field, $id ); ?>
			<?php
			if ( $field['description'] ) :
				?>
				<small class="mrnf-field__description"><?php echo esc_html( $field['description'] ); ?></small><?php endif; ?>
			<span class="mrnf-field__error" id="<?php echo esc_attr( $id ); ?>-error" aria-live="polite"></span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render the input control.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $id HTML ID.
	 * @return void
	 */
	private function control( array $field, string $id ): void {
		$type     = $field['type'];
		$key      = $field['key'];
		$name     = 'mrnf[' . $key . ']';
		$required = ! empty( $field['required'] );
		$attrs    = $required ? ' required aria-required="true"' : '';
		$attrs   .= $field['placeholder'] ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
		$rules    = (array) $field['validation'];
		if ( ! empty( $rules['minLength'] ) ) {
			$attrs .= ' minlength="' . absint( $rules['minLength'] ) . '"';
		}
		if ( ! empty( $rules['maxLength'] ) ) {
			$attrs .= ' maxlength="' . absint( $rules['maxLength'] ) . '"';
		}
		if ( ! empty( $rules['pattern'] ) ) {
			$attrs .= ' pattern="' . esc_attr( $rules['pattern'] ) . '"';
		}

		if ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="5"' . $attrs . '>' . esc_textarea( $field['default'] ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are built from escaped values.
			return;
		}
		if ( 'select' === $type ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $attrs . '><option value="">' . esc_html( $field['placeholder'] ? $field['placeholder'] : __( 'انتخاب کنید', 'mrn-form' ) ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $field['choices'] as $choice ) {
				echo '<option value="' . esc_attr( $choice ) . '"' . selected( $field['default'], $choice, false ) . '>' . esc_html( $choice ) . '</option>';
			}
			echo '</select>';
			return;
		}
		if ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
			echo '<div class="mrnf-options" id="' . esc_attr( $id ) . '">';
			foreach ( $field['choices'] as $index => $choice ) {
				$choice_id = $id . '-' . $index;
				$inputname = 'checkbox' === $type ? $name . '[]' : $name;
				echo '<label><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $choice_id ) . '" name="' . esc_attr( $inputname ) . '" value="' . esc_attr( $choice ) . '"' . ( $required && 0 === $index ? ' required' : '' ) . '><span>' . esc_html( $choice ) . '</span></label>';
			}
			echo '</div>';
			return;
		}
		if ( 'consent' === $type ) {
			echo '<label class="mrnf-consent"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . $attrs . '><span>' . esc_html( $field['label'] ) . ( $required ? ' *' : '' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( 'file' === $type ) {
			$accept = implode( ',', array_map( static fn( $ext ): string => '.' . sanitize_key( $ext ), explode( ',', $rules['extensions'] ?? '' ) ) );
			echo '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" accept="' . esc_attr( $accept ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}
		if ( 'hidden' === $type ) {
			echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $field['default'] ) . '">';
			return;
		}

		$html_type = in_array( $type, array( 'text', 'email', 'tel', 'number', 'date' ), true ) ? $type : 'text';
		if ( 'number' === $type ) {
			$attrs .= '' !== (string) ( $rules['min'] ?? '' ) ? ' min="' . esc_attr( $rules['min'] ) . '"' : '';
			$attrs .= '' !== (string) ( $rules['max'] ?? '' ) ? ' max="' . esc_attr( $rules['max'] ) . '"' : '';
		}
		echo '<input type="' . esc_attr( $html_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $field['default'] ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
