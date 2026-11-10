<?php
/**
 * Administration screens.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Manage forms, entries, reporting, and settings.
 */
final class Admin {
	private const PAGE = 'mrn-form';

	/**
	 * Create the administration controller.
	 *
	 * @param Form_Repository  $forms Form storage.
	 * @param Entry_Repository $entries Entry storage.
	 */
	public function __construct(
		private Form_Repository $forms,
		private Entry_Repository $entries
	) {}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_mrnf_save_form', array( $this, 'save_form' ) );
		add_action( 'admin_post_mrnf_form_action', array( $this, 'form_action' ) );
		add_action( 'admin_post_mrnf_entry_action', array( $this, 'entry_action' ) );
		add_action( 'admin_post_mrnf_export_entries', array( $this, 'export_entries' ) );
		add_action( 'admin_post_mrnf_save_settings', array( $this, 'save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MRNF_FILE ), array( $this, 'plugin_links' ) );
	}

	/**
	 * Register navigation.
	 *
	 * @return void
	 */
	public function menu(): void {
		$capability = 'manage_options';
		add_menu_page( __( 'MRN Forms', 'mrn-forms' ), __( 'Forms', 'mrn-forms' ), $capability, self::PAGE, array( $this, 'render_dashboard' ), MRNF_URL . 'assets/images/logo-menu.png' );
		add_submenu_page( self::PAGE, __( 'Forms dashboard', 'mrn-forms' ), __( 'Dashboard', 'mrn-forms' ), $capability, self::PAGE, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::PAGE, __( 'All forms', 'mrn-forms' ), __( 'All forms', 'mrn-forms' ), $capability, self::PAGE . '-forms', array( $this, 'render_forms' ) );
		add_submenu_page( self::PAGE, __( 'New form', 'mrn-forms' ), __( 'New form', 'mrn-forms' ), $capability, self::PAGE . '-builder', array( $this, 'render_builder' ) );
		add_submenu_page( self::PAGE, __( 'Inbox', 'mrn-forms' ), __( 'Inbox', 'mrn-forms' ), $capability, self::PAGE . '-entries', array( $this, 'render_entries' ) );
		add_submenu_page( self::PAGE, __( 'MRN Forms settings', 'mrn-forms' ), __( 'Settings', 'mrn-forms' ), $capability, self::PAGE . '-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Load the menu icon and administration assets.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		wp_enqueue_style( 'mrnf-menu', MRNF_URL . 'assets/css/menu.css', array(), MRNF_VERSION );
		if ( ! str_contains( $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'mrnf-admin', MRNF_URL . 'assets/css/admin.css', array(), MRNF_VERSION );
		wp_enqueue_script( 'mrnf-admin', MRNF_URL . 'assets/js/admin.js', array(), MRNF_VERSION, true );
		wp_localize_script(
			'mrnf-admin',
			'mrnfAdmin',
			array(
				'fieldTypes'   => Field_Registry::all(),
				'starterField' => Field_Registry::normalize( 'text' ),
				'direction'    => is_rtl() ? 'rtl' : 'ltr',
				'i18n'         => array(
					'addChoice'                 => __( 'Add option', 'mrn-forms' ),
					'allowedExtensions'         => __( 'Allowed extensions', 'mrn-forms' ),
					'addAlternativeField'       => __( 'Add another input field before using a dependent requirement.', 'mrn-forms' ),
					'alternativeField'          => __( 'Alternative field', 'mrn-forms' ),
					'alwaysRequired'            => __( 'Always required', 'mrn-forms' ),
					'alwaysRequiredHelp'        => __( 'Users must always complete this field.', 'mrn-forms' ),
					'choice'                    => __( 'Option', 'mrn-forms' ),
					'choice1'                   => __( 'Option 1', 'mrn-forms' ),
					'choice2'                   => __( 'Option 2', 'mrn-forms' ),
					'choice3'                   => __( 'Option 3', 'mrn-forms' ),
					'choices'                   => __( 'Options', 'mrn-forms' ),
					'conditionalLogic'          => __( 'Conditional logic', 'mrn-forms' ),
					'conditionallyRequired'     => __( 'Required when another field is empty', 'mrn-forms' ),
					'conditionallyRequiredHelp' => __( 'This field is required based on the alternative field value.', 'mrn-forms' ),
					'confirmAction'             => __( 'This action cannot be undone. Continue?', 'mrn-forms' ),
					'content'                   => __( 'Content', 'mrn-forms' ),
					'copySuccess'               => __( 'Copied', 'mrn-forms' ),
					'defaultValue'              => __( 'Default value', 'mrn-forms' ),
					'delete'                    => __( 'Delete', 'mrn-forms' ),
					'deleteField'               => __( 'Delete this field?', 'mrn-forms' ),
					'description'               => __( 'Help text', 'mrn-forms' ),
					'duplicate'                 => __( 'Duplicate', 'mrn-forms' ),
					'emptyCanvas'               => __( 'Drag fields from the sidebar or click them.', 'mrn-forms' ),
					/* translators: %d: Number of fields in the form. */
					'fieldCount'                => __( '%d fields', 'mrn-forms' ),
					'fieldLabel'                => __( 'Label', 'mrn-forms' ),
					'fieldWidth'                => __( 'Field width', 'mrn-forms' ),
					'fileSelect'                => __( 'Choose a file…', 'mrn-forms' ),
					/* translators: %s: Field key used in a merge tag. */
					'helpMergeTag'              => __( 'Merge tag: {field:%s}', 'mrn-forms' ),
					'hidden'                    => __( 'Hidden', 'mrn-forms' ),
					/* translators: %s: Field type label. */
					'input'                     => __( '%s input', 'mrn-forms' ),
					'layout'                    => __( 'Layout', 'mrn-forms' ),
					'maxFileSize'               => __( 'Maximum size (MB)', 'mrn-forms' ),
					'maximum'                   => __( 'Maximum', 'mrn-forms' ),
					'maxLength'                 => __( 'Maximum characters', 'mrn-forms' ),
					'minimum'                   => __( 'Minimum', 'mrn-forms' ),
					'minLength'                 => __( 'Minimum characters', 'mrn-forms' ),
					'newSection'                => __( 'A new section heading', 'mrn-forms' ),
					'noOptions'                 => __( 'No options', 'mrn-forms' ),
					'noValue'                   => __( 'No value', 'mrn-forms' ),
					'optionalField'             => __( 'Optional', 'mrn-forms' ),
					'optionalFieldHelp'         => __( 'Users may leave this field empty.', 'mrn-forms' ),
					'pattern'                   => __( 'RegEx pattern', 'mrn-forms' ),
					'placeholderContent'        => __( 'Descriptive text or HTML', 'mrn-forms' ),
					'placeholderLong'           => __( 'Long text…', 'mrn-forms' ),
					'requiredField'             => __( 'This field is required', 'mrn-forms' ),
					'requiredWithoutHelp'       => __( 'If the alternative field is empty, this field becomes required.', 'mrn-forms' ),
					'requirementMode'           => __( 'Requirement mode', 'mrn-forms' ),
					'selectField'               => __( 'Select a field to edit its settings.', 'mrn-forms' ),
					'selectFieldOption'         => __( 'Select a field…', 'mrn-forms' ),
					'showConditionally'         => __( 'Show this field conditionally', 'mrn-forms' ),
					'startsHere'                => __( 'Your form starts here', 'mrn-forms' ),
					'uniqueKey'                 => __( 'Unique key', 'mrn-forms' ),
					'unsaved'                   => __( 'You have unsaved changes.', 'mrn-forms' ),
					'validation'                => __( 'Validation', 'mrn-forms' ),
					'width25'                   => __( '25%', 'mrn-forms' ),
					'width33'                   => __( '33%', 'mrn-forms' ),
					'width50'                   => __( '50%', 'mrn-forms' ),
					'width66'                   => __( '66%', 'mrn-forms' ),
					'width75'                   => __( '75%', 'mrn-forms' ),
					'width100'                  => __( '100%', 'mrn-forms' ),
					'condition'                 => __( 'Condition', 'mrn-forms' ),
					'compareValue'              => __( 'Comparison value', 'mrn-forms' ),
					'equals'                    => __( 'Equals', 'mrn-forms' ),
					'notEquals'                 => __( 'Does not equal', 'mrn-forms' ),
					'contains'                  => __( 'Contains', 'mrn-forms' ),
					'notEmpty'                  => __( 'Is not empty', 'mrn-forms' ),
					'empty'                     => __( 'Is empty', 'mrn-forms' ),
					'sourceField'               => __( 'Source field', 'mrn-forms' ),
				),
			)
		);
	}

	/**
	 * Add dashboard shortcut on Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function plugin_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Dashboard', 'mrn-forms' ) . '</a>' );
		return $links;
	}

	/**
	 * Operations dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->authorize();
		$forms        = $this->forms->count();
		$published    = $this->forms->count( 'published' );
		$entries      = $this->entries->count();
		$unread       = $this->entries->count( 0, 'unread' );
		$latest       = $this->entries->all( array( 'limit' => 6 ) );
		$active_forms = $this->forms->all(
			array(
				'status' => 'published',
				'limit'  => 5,
			)
		);
		$this->header( 'dashboard' );
		$this->notice();
		?>
		<main class="mrnf-admin__content">
			<section class="mrnf-stats">
				<?php
				/* translators: %d: number of published forms. */
				$this->stat( __( 'Total forms', 'mrn-forms' ), $forms, sprintf( __( '%d published forms', 'mrn-forms' ), $published ), 'forms' );
				?>
				<?php $this->stat( __( 'Total entries', 'mrn-forms' ), $entries, __( 'Secure, exportable archive', 'mrn-forms' ), 'entries' ); ?>
				<?php $this->stat( __( 'Unread', 'mrn-forms' ), $unread, __( 'Needs your review', 'mrn-forms' ), 'unread' ); ?>
				<?php $this->stat( __( 'Email notification', 'mrn-forms' ), __( 'Active', 'mrn-forms' ), __( 'Administrator and form submitter', 'mrn-forms' ), 'mail' ); ?>
			</section>
			<section class="mrnf-dashboard-grid">
				<article class="mrnf-panel">
					<div class="mrnf-panel__head">
						<div><span><?php esc_html_e( 'Recent activity', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Latest entries', 'mrn-forms' ); ?></h2></div>
						<a class="mrnf-button" href="<?php echo esc_url( $this->page_url( 'entries' ) ); ?>"><?php esc_html_e( 'View inbox', 'mrn-forms' ); ?></a>
					</div>
					<?php $this->entries_table( $latest, true ); ?>
				</article>
				<aside class="mrnf-panel mrnf-quick">
					<span class="mrnf-kicker"><?php esc_html_e( 'Quick start', 'mrn-forms' ); ?></span>
					<h2><?php esc_html_e( 'A smooth way to collect information', 'mrn-forms' ); ?></h2>
					<p><?php esc_html_e( 'Build your form, match it to your theme, and display it with the shortcode or MRN Forms block.', 'mrn-forms' ); ?></p>
					<a class="mrnf-button mrnf-button--primary mrnf-button--large" href="<?php echo esc_url( $this->page_url( 'builder' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'Create a new form', 'mrn-forms' ); ?></a>
					<div class="mrnf-health">
						<div class="<?php echo $published ? 'is-ok' : ''; ?>"><i></i><span><b><?php esc_html_e( 'Published form', 'mrn-forms' ); ?></b><small>
						<?php
						/* translators: %d: number of forms ready to collect entries. */
						echo $published ? esc_html( sprintf( __( '%d forms ready to collect entries', 'mrn-forms' ), $published ) ) : esc_html__( 'No forms have been published yet', 'mrn-forms' );
						?>
						</small></span></div>
						<div class="is-ok"><i></i><span><b><?php esc_html_e( 'Dedicated storage', 'mrn-forms' ); ?></b><small><?php esc_html_e( 'Optimized, independent tables', 'mrn-forms' ); ?></small></span></div>
						<div class="is-ok"><i></i><span><b><?php esc_html_e( 'Spam protection', 'mrn-forms' ); ?></b><small><?php esc_html_e( 'Honeypot and rate limiting', 'mrn-forms' ); ?></small></span></div>
					</div>
				</aside>
			</section>
			<?php if ( $active_forms ) : ?>
				<section class="mrnf-panel">
					<div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Online forms', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Quick access and embed code', 'mrn-forms' ); ?></h2></div></div>
					<div class="mrnf-shortcode-grid">
						<?php foreach ( $active_forms as $form ) : ?>
							<article><div><b><?php echo esc_html( $form['title'] ); ?></b><small>
							<?php
							/* translators: %d: number of entries for a form. */
							echo esc_html( sprintf( __( '%d entries', 'mrn-forms' ), $this->entries->count( $form['id'] ) ) );
							?>
							</small></div><code>[mrn_form id="<?php echo esc_html( (string) $form['id'] ); ?>"]</code><button type="button" data-mrnf-copy="[mrn_form id=&quot;<?php echo esc_attr( (string) $form['id'] ); ?>&quot;]"><span class="dashicons dashicons-admin-page"></span></button></article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Forms library.
	 *
	 * @return void
	 */
	public function render_forms(): void {
		$this->authorize();
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$forms  = $this->forms->all( array( 'search' => $search ) );
		$this->header( 'forms' );
		$this->notice();
		?>
		<main class="mrnf-admin__content">
			<section class="mrnf-panel">
				<div class="mrnf-panel__head">
					<div><span><?php esc_html_e( 'Library', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'All forms', 'mrn-forms' ); ?></h2><p><?php esc_html_e( 'Create, edit, duplicate, and manage forms in one workspace.', 'mrn-forms' ); ?></p></div>
					<a class="mrnf-button mrnf-button--primary" href="<?php echo esc_url( $this->page_url( 'builder' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'New form', 'mrn-forms' ); ?></a>
				</div>
				<form class="mrnf-search" method="get"><input type="hidden" name="page" value="mrn-form-forms"><input name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search forms…', 'mrn-forms' ); ?>"><button class="mrnf-button" type="submit"><?php esc_html_e( 'Search', 'mrn-forms' ); ?></button></form>
				<div class="mrnf-table-wrap">
					<table class="mrnf-table">
						<thead><tr><th><?php esc_html_e( 'Form', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Status', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Entries', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Embed code', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Last updated', 'mrn-forms' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						if ( ! $forms ) :
							?>
							<tr><td colspan="6"><div class="mrnf-empty"><span class="dashicons dashicons-feedback"></span><b><?php esc_html_e( 'You do not have any forms yet', 'mrn-forms' ); ?></b><p><?php esc_html_e( 'Create your first form in minutes.', 'mrn-forms' ); ?></p></div></td></tr><?php endif; ?>
						<?php foreach ( $forms as $form ) : ?>
							<tr>
								<td><a class="mrnf-table__title" href="<?php echo esc_url( add_query_arg( 'id', $form['id'], $this->page_url( 'builder' ) ) ); ?>"><?php echo esc_html( $form['title'] ); ?></a><small>#<?php echo esc_html( (string) $form['id'] ); ?> · <?php echo esc_html( $form['slug'] ); ?></small></td>
								<td><?php echo $this->status_badge( $form['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( 'form_id', $form['id'], $this->page_url( 'entries' ) ) ); ?>"><?php echo esc_html( (string) $this->entries->count( $form['id'] ) ); ?></a></td>
								<td><code>[mrn_form id="<?php echo esc_html( (string) $form['id'] ); ?>"]</code></td>
								<td><?php /* translators: %s: elapsed time since the form was updated or the entry was created. */ printf( esc_html__( '%s ago', 'mrn-forms' ), esc_html( human_time_diff( strtotime( $form['updated_at'] . ' UTC' ), time() ) ) ); ?></td>
								<td><div class="mrnf-row-actions"><a href="<?php echo esc_url( add_query_arg( 'id', $form['id'], $this->page_url( 'builder' ) ) ); ?>"><?php esc_html_e( 'Edit', 'mrn-forms' ); ?></a><a href="<?php echo esc_url( $this->form_action_url( $form['id'], 'duplicate' ) ); ?>"><?php esc_html_e( 'Duplicate', 'mrn-forms' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->form_action_url( $form['id'], 'delete' ) ); ?>"><?php esc_html_e( 'Delete', 'mrn-forms' ); ?></a></div></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Form builder workspace.
	 *
	 * @return void
	 */
	public function render_builder(): void {
		$this->authorize();
		$id   = absint( $_GET['id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only form selector.
		$form = $id ? $this->forms->find( $id ) : null;
		if ( ! $form ) {
			$form = array(
				'id'            => 0,
				'title'         => __( 'New contact form', 'mrn-forms' ),
				'slug'          => '',
				'status'        => 'draft',
				'description'   => __( 'Please complete the form below and we will contact you as soon as possible.', 'mrn-forms' ),
				'fields'        => Field_Registry::starter_fields(),
				'settings'      => Form_Repository::default_settings(),
				'notifications' => Form_Repository::default_notifications(),
			);
		}
		$this->header( 'builder', $form['id'] ? __( 'Edit form', 'mrn-forms' ) : __( 'New form', 'mrn-forms' ) );
		$this->notice();
		?>
		<main class="mrnf-builder" data-mrnf-builder>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-mrnf-builder-form>
				<input type="hidden" name="action" value="mrnf_save_form">
				<input type="hidden" name="id" value="<?php echo esc_attr( (string) $form['id'] ); ?>">
				<input type="hidden" name="fields_json" value="<?php echo esc_attr( Database::encode( $form['fields'] ) ); ?>" data-mrnf-fields-json>
				<input type="hidden" name="settings_json" value="<?php echo esc_attr( Database::encode( $form['settings'] ) ); ?>" data-mrnf-settings-json>
				<input type="hidden" name="notifications_json" value="<?php echo esc_attr( Database::encode( $form['notifications'] ) ); ?>" data-mrnf-notifications-json>
				<?php wp_nonce_field( 'mrnf_save_form' ); ?>
				<div class="mrnf-builder__top">
					<div class="mrnf-builder__identity"><input name="title" value="<?php echo esc_attr( $form['title'] ); ?>" aria-label="<?php esc_attr_e( 'Form title', 'mrn-forms' ); ?>" required><span>#<?php echo $form['id'] ? esc_html( (string) $form['id'] ) : esc_html__( 'New', 'mrn-forms' ); ?></span></div>
					<div class="mrnf-builder__actions">
						<select name="status" aria-label="<?php esc_attr_e( 'Form status', 'mrn-forms' ); ?>"><option value="draft" <?php selected( $form['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'mrn-forms' ); ?></option><option value="published" <?php selected( $form['status'], 'published' ); ?>><?php esc_html_e( 'Published', 'mrn-forms' ); ?></option><option value="archived" <?php selected( $form['status'], 'archived' ); ?>><?php esc_html_e( 'Archived', 'mrn-forms' ); ?></option></select>
						<?php
						if ( $form['id'] ) :
							?>
							<button type="button" class="mrnf-button" data-mrnf-preview><?php esc_html_e( 'Preview', 'mrn-forms' ); ?></button><?php endif; ?>
						<button type="submit" class="mrnf-button mrnf-button--primary"><?php esc_html_e( 'Save form', 'mrn-forms' ); ?></button>
					</div>
				</div>
				<div class="mrnf-builder__workspace">
					<aside class="mrnf-builder__palette">
						<div class="mrnf-builder__panel-title"><span><?php esc_html_e( 'Fields', 'mrn-forms' ); ?></span><small><?php esc_html_e( 'Click or drag to add', 'mrn-forms' ); ?></small></div>
						<div class="mrnf-palette">
							<?php foreach ( Field_Registry::all() as $type => $definition ) : ?>
								<button type="button" draggable="true" data-mrnf-add-field="<?php echo esc_attr( $type ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $definition['icon'] ); ?>"></span><b><?php echo esc_html( $definition['label'] ); ?></b></button>
							<?php endforeach; ?>
						</div>
						<div class="mrnf-builder__tips"><b><?php esc_html_e( 'Display on site', 'mrn-forms' ); ?></b><code>[mrn_form id="<?php echo $form['id'] ? esc_html( (string) $form['id'] ) : 'ID'; ?>"]</code><small><?php esc_html_e( 'Or use the MRN Forms block or the mrn_form() function.', 'mrn-forms' ); ?></small></div>
					</aside>
					<section class="mrnf-builder__stage">
						<div class="mrnf-builder__tabs"><button type="button" class="is-active" data-mrnf-tab="fields"><?php esc_html_e( 'Structure', 'mrn-forms' ); ?></button><button type="button" data-mrnf-tab="appearance"><?php esc_html_e( 'Appearance and behavior', 'mrn-forms' ); ?></button><button type="button" data-mrnf-tab="notifications"><?php esc_html_e( 'Notifications', 'mrn-forms' ); ?></button></div>
						<div class="mrnf-builder__tab is-active" data-mrnf-tab-panel="fields">
							<div class="mrnf-canvas-head"><div><input name="description" value="<?php echo esc_attr( $form['description'] ); ?>" placeholder="<?php esc_attr_e( 'Short form description…', 'mrn-forms' ); ?>"></div><span data-mrnf-field-count></span></div>
							<div class="mrnf-canvas" data-mrnf-canvas></div>
						</div>
						<div class="mrnf-builder__tab" data-mrnf-tab-panel="appearance">
							<?php $this->appearance_panel( $form['settings'] ); ?>
							<?php $this->anar_panel( $form['settings'] ); ?>
						</div>
						<div class="mrnf-builder__tab" data-mrnf-tab-panel="notifications">
								<?php $this->notifications_panel( $form['notifications'], $form['fields'] ); ?>
						</div>
					</section>
					<aside class="mrnf-builder__inspector">
						<div class="mrnf-builder__panel-title"><span><?php esc_html_e( 'Field settings', 'mrn-forms' ); ?></span><small><?php esc_html_e( 'Changes are applied immediately', 'mrn-forms' ); ?></small></div>
						<div data-mrnf-inspector><div class="mrnf-inspector-empty"><span class="dashicons dashicons-admin-generic"></span><p><?php esc_html_e( 'Select a field to configure it.', 'mrn-forms' ); ?></p></div></div>
					</aside>
				</div>
			</form>
			<div class="mrnf-preview-modal" data-mrnf-preview-modal hidden><div><button type="button" data-mrnf-close-preview>×</button><iframe title="<?php esc_attr_e( 'Form preview', 'mrn-forms' ); ?>"></iframe></div></div>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Inbox and entry details.
	 *
	 * @return void
	 */
	public function render_entries(): void {
		$this->authorize();
		$entry_id = absint( $_GET['entry_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $entry_id ) {
			$this->render_entry_detail( $entry_id );
			return;
		}
		$form_id = absint( $_GET['form_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status  = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entries = $this->entries->all(
			array(
				'form_id' => $form_id,
				'status'  => $status,
				'limit'   => 100,
			)
		);
		$forms   = $this->forms->all();
		$this->header( 'entries' );
		$this->notice();
		?>
		<main class="mrnf-admin__content">
			<section class="mrnf-panel">
				<div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Saved entries', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Inbox', 'mrn-forms' ); ?></h2><p><?php esc_html_e( 'Review, organize, and export submissions.', 'mrn-forms' ); ?></p></div><a class="mrnf-button" href="
				<?php
				echo esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action'  => 'mrnf_export_entries',
								'form_id' => $form_id,
							),
							admin_url( 'admin-post.php' )
						),
						'mrnf_export_entries'
					)
				);
				?>
															"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Export CSV', 'mrn-forms' ); ?></a></div>
				<form class="mrnf-filters" method="get"><input type="hidden" name="page" value="mrn-form-entries"><select name="form_id"><option value="0"><?php esc_html_e( 'All forms', 'mrn-forms' ); ?></option>
				<?php
				foreach ( $forms as $form ) :
					?>
					<option value="<?php echo esc_attr( (string) $form['id'] ); ?>" <?php selected( $form_id, $form['id'] ); ?>><?php echo esc_html( $form['title'] ); ?></option><?php endforeach; ?></select><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'mrn-forms' ); ?></option><option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'mrn-forms' ); ?></option><option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'Read', 'mrn-forms' ); ?></option><option value="spam" <?php selected( $status, 'spam' ); ?>><?php esc_html_e( 'Spam', 'mrn-forms' ); ?></option></select><button class="mrnf-button" type="submit"><?php esc_html_e( 'Apply filter', 'mrn-forms' ); ?></button></form>
				<?php $this->entries_table( $entries ); ?>
			</section>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Global email and behavior settings.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$this->authorize();
		$settings = Settings::all();
		$this->header( 'settings' );
		$this->notice();
		?>
		<main class="mrnf-admin__content">
			<form class="mrnf-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="mrnf_save_settings"><?php wp_nonce_field( 'mrnf_save_settings' ); ?>
				<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Email identity', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Sender and brand', 'mrn-forms' ); ?></h2><p><?php esc_html_e( 'Sender details and HTML template colors for every email.', 'mrn-forms' ); ?></p></div></div><div class="mrnf-field-grid">
					<?php $this->setting_field( 'brand_name', __( 'Brand name', 'mrn-forms' ), $settings['brand_name'] ); ?>
					<?php $this->setting_field( 'email_logo', __( 'Logo URL', 'mrn-forms' ), $settings['email_logo'], 'url', __( 'A square HTTPS image is recommended.', 'mrn-forms' ) ); ?>
					<?php $this->setting_field( 'from_name', __( 'Sender name', 'mrn-forms' ), $settings['from_name'] ); ?>
					<?php $this->setting_field( 'from_email', __( 'Sender email', 'mrn-forms' ), $settings['from_email'], 'email' ); ?>
					<?php $this->setting_field( 'admin_email', __( 'Default administrator email', 'mrn-forms' ), $settings['admin_email'], 'email', __( 'Available in merge tags as {admin_email}.', 'mrn-forms' ) ); ?>
				</div><div class="mrnf-color-row"><label><span><?php esc_html_e( 'Primary color', 'mrn-forms' ); ?></span><input type="color" name="settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></label><label><span><?php esc_html_e( 'Accent color', 'mrn-forms' ); ?></span><input type="color" name="settings[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></label></div></section>
				<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Security and data', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Submission and data-deletion controls', 'mrn-forms' ); ?></h2></div></div><div class="mrnf-field-grid"><?php $this->setting_field( 'rate_limit', __( 'Maximum submissions per 10 minutes', 'mrn-forms' ), $settings['rate_limit'], 'number', __( 'Between 1 and 50 per IP address and form.', 'mrn-forms' ) ); ?></div><?php $this->toggle( 'delete_on_uninstall', __( 'Delete all data on uninstall', 'mrn-forms' ), __( 'Forms, entries, email logs, and settings will be deleted permanently.', 'mrn-forms' ), (bool) $settings['delete_on_uninstall'], true ); ?></section>
				<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Privacy', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Traffic attribution and analytics events', 'mrn-forms' ); ?></h2><p><?php esc_html_e( 'Both features are off by default. Set up privacy notices and any required consent on your site before enabling them.', 'mrn-forms' ); ?></p></div></div>
					<?php $this->toggle( 'collect_attribution', __( 'Collect traffic attribution', 'mrn-forms' ), __( 'Source, campaign and landing page are stored in browser session storage and with form submissions on this site.', 'mrn-forms' ), (bool) $settings['collect_attribution'] ); ?>
					<?php $this->toggle( 'analytics_events', __( 'Send events to Google Analytics', 'mrn-forms' ), __( 'Send phone, WhatsApp and successful submission events to the existing site gtag. The plugin does not install Google scripts or send form answers or contact numbers.', 'mrn-forms' ), (bool) $settings['analytics_events'] ); ?>
				</section>
				<div class="mrnf-settings__save"><button class="mrnf-button mrnf-button--primary mrnf-button--large" type="submit"><?php esc_html_e( 'Save settings', 'mrn-forms' ); ?></button></div>
			</form>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Persist a builder form.
	 *
	 * @return void
	 */
	public function save_form(): void {
		$this->authorize();
		check_admin_referer( 'mrnf_save_form' );
		$fields        = json_decode( wp_unslash( $_POST['fields_json'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Form_Repository normalizes every decoded field.
		$settings      = json_decode( wp_unslash( $_POST['settings_json'] ?? '{}' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Form_Repository sanitizes every decoded setting.
		$notifications = json_decode( wp_unslash( $_POST['notifications_json'] ?? '[]' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Form_Repository sanitizes every decoded notification.
		$result        = $this->forms->save(
			array(
				'id'            => absint( $_POST['id'] ?? 0 ),
				'title'         => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
				'status'        => sanitize_key( $_POST['status'] ?? 'draft' ),
				'description'   => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
				'fields'        => is_array( $fields ) ? $fields : array(),
				'settings'      => is_array( $settings ) ? $settings : array(),
				'notifications' => is_array( $notifications ) ? $notifications : array(),
			)
		);
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
			$this->redirect( 'forms' );
		}
		$this->set_notice( 'success', __( 'Form saved successfully.', 'mrn-forms' ) );
		wp_safe_redirect( add_query_arg( 'id', $result, $this->page_url( 'builder' ) ) );
		exit;
	}

	/**
	 * Delete or duplicate a form.
	 *
	 * @return void
	 */
	public function form_action(): void {
		$this->authorize();
		$id     = absint( $_GET['id'] ?? 0 );
		$action = sanitize_key( $_GET['do'] ?? '' );
		check_admin_referer( 'mrnf_form_action_' . $id . '_' . $action );
		if ( 'delete' === $action ) {
			$this->forms->delete( $id );
			$this->set_notice( 'success', __( 'The form and its related entries were deleted.', 'mrn-forms' ) );
		} elseif ( 'duplicate' === $action ) {
			$result = $this->forms->duplicate( $id );
			$this->set_notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'A draft copy was created.', 'mrn-forms' ) );
		}
		$this->redirect( 'forms' );
	}

	/**
	 * Change or delete an entry.
	 *
	 * @return void
	 */
	public function entry_action(): void {
		$this->authorize();
		$id     = absint( $_GET['id'] ?? 0 );
		$action = sanitize_key( $_GET['do'] ?? '' );
		check_admin_referer( 'mrnf_entry_action_' . $id . '_' . $action );
		if ( 'delete' === $action ) {
			$this->entries->delete( $id );
			$this->set_notice( 'success', __( 'Entry deleted.', 'mrn-forms' ) );
		} elseif ( in_array( $action, array( 'read', 'unread', 'spam' ), true ) ) {
			$this->entries->set_status( $id, $action );
			$this->set_notice( 'success', __( 'Entry status updated.', 'mrn-forms' ) );
		}
		$this->redirect( 'entries' );
	}

	/**
	 * Generate a UTF-8 BOM CSV compatible with spreadsheet applications.
	 *
	 * @return void
	 */
	public function export_entries(): void {
		$this->authorize();
		check_admin_referer( 'mrnf_export_entries' );
		$form_id = absint( $_GET['form_id'] ?? 0 );
		$entries = $this->entries->all(
			array(
				'form_id' => $form_id,
				'limit'   => 5000,
			)
		);
		$keys    = array();
		foreach ( $entries as $entry ) {
			$keys = array_unique( array_merge( $keys, array_keys( $entry['values'] ) ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=mrn-form-entries-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'wb' );
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a generated download, not writing the filesystem.
		fputcsv( $output, array_map( array( CSV_Export::class, 'cell' ), array_merge( array( 'ID', 'Form', 'Status', 'Created', 'Traffic source', 'Medium', 'Campaign', 'Landing page', 'External referrer' ), $keys ) ), ',', '"', '' );
		foreach ( $entries as $entry ) {
			$attribution = (array) ( $entry['attribution'] ?? array() );
			$row         = array(
				$entry['id'],
				$entry['form_title'],
				$entry['status'],
				$entry['created_at'],
				Attribution::label( $attribution ),
				$attribution['medium'] ?? '',
				$attribution['campaign'] ?? '',
				$attribution['landing_page'] ?? '',
				$attribution['referrer'] ?? '',
			);
			foreach ( $keys as $key ) {
				$value = $entry['values'][ $key ] ?? '';
				$row[] = is_array( $value ) ? implode( ' | ', $value ) : $value;
			}
			fputcsv( $output, array_map( array( CSV_Export::class, 'cell' ), $row ), ',', '"', '' );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the streamed response.
		exit;
	}

	/**
	 * Save global settings.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->authorize();
		check_admin_referer( 'mrnf_save_settings' );
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Settings::sanitize validates every member.
		update_option( Settings::OPTION, Settings::sanitize( $input ) );
		$this->set_notice( 'success', __( 'Settings saved.', 'mrn-forms' ) );
		$this->redirect( 'settings' );
	}

	/**
	 * Builder appearance settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return void
	 */
	private function appearance_panel( array $settings ): void {
		?>
		<div class="mrnf-config-grid">
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-admin-appearance"></span><div><b><?php esc_html_e( 'Form appearance', 'mrn-forms' ); ?></b><small><?php esc_html_e( 'Compatible with any MRN theme and customizable with CSS', 'mrn-forms' ); ?></small></div></div>
				<div class="mrnf-field-grid">
					<label class="mrnf-input"><span><?php esc_html_e( 'Primary color', 'mrn-forms' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['primaryColor'] ); ?>" data-mrnf-setting="primaryColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Accent color', 'mrn-forms' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['accentColor'] ); ?>" data-mrnf-setting="accentColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Background', 'mrn-forms' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['backgroundColor'] ); ?>" data-mrnf-setting="backgroundColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Text color', 'mrn-forms' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['textColor'] ); ?>" data-mrnf-setting="textColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Field spacing (px)', 'mrn-forms' ); ?></span><input type="number" min="0" max="48" value="<?php echo esc_attr( (string) $settings['layoutGap'] ); ?>" data-mrnf-setting="layoutGap"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Corner radius (px)', 'mrn-forms' ); ?></span><input type="number" min="0" max="40" value="<?php echo esc_attr( (string) $settings['borderRadius'] ); ?>" data-mrnf-setting="borderRadius"></label>
				</div>
			</section>
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-editor-alignleft"></span><div><b><?php esc_html_e( 'Layout and text', 'mrn-forms' ); ?></b></div></div>
				<div class="mrnf-field-grid">
					<label class="mrnf-input"><span><?php esc_html_e( 'Button text', 'mrn-forms' ); ?></span><input value="<?php echo esc_attr( $settings['submitLabel'] ); ?>" data-mrnf-setting="submitLabel"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Label position', 'mrn-forms' ); ?></span><select data-mrnf-setting="labelPosition"><option value="top" <?php selected( $settings['labelPosition'], 'top' ); ?>><?php esc_html_e( 'Above the field', 'mrn-forms' ); ?></option><option value="inline" <?php selected( $settings['labelPosition'], 'inline' ); ?>><?php esc_html_e( 'Beside the field', 'mrn-forms' ); ?></option><option value="hidden" <?php selected( $settings['labelPosition'], 'hidden' ); ?>><?php esc_html_e( 'Hidden', 'mrn-forms' ); ?></option></select></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Direction', 'mrn-forms' ); ?></span><select data-mrnf-setting="direction"><option value="auto" <?php selected( $settings['direction'], 'auto' ); ?>><?php esc_html_e( 'Automatic', 'mrn-forms' ); ?></option><option value="rtl" <?php selected( $settings['direction'], 'rtl' ); ?>>RTL</option><option value="ltr" <?php selected( $settings['direction'], 'ltr' ); ?>>LTR</option></select></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Custom CSS class', 'mrn-forms' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $settings['customClass'] ); ?>" data-mrnf-setting="customClass"></label>
				</div>
				<div class="mrnf-toggle-list">
					<?php $this->builder_toggle( 'showTitle', __( 'Show form title', 'mrn-forms' ), $settings['showTitle'] ); ?>
					<?php $this->builder_toggle( 'showDescription', __( 'Show form description', 'mrn-forms' ), $settings['showDescription'] ); ?>
					<?php $this->builder_toggle( 'buttonFullWidth', __( 'Full-width button', 'mrn-forms' ), $settings['buttonFullWidth'] ); ?>
				</div>
			</section>
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-yes-alt"></span><div><b><?php esc_html_e( 'After submission', 'mrn-forms' ); ?></b></div></div>
				<label class="mrnf-input"><span><?php esc_html_e( 'Success message', 'mrn-forms' ); ?></span><textarea data-mrnf-setting="successMessage"><?php echo esc_textarea( $settings['successMessage'] ); ?></textarea></label>
				<label class="mrnf-input"><span><?php esc_html_e( 'Error message', 'mrn-forms' ); ?></span><textarea data-mrnf-setting="errorMessage"><?php echo esc_textarea( $settings['errorMessage'] ); ?></textarea></label>
				<label class="mrnf-input"><span><?php esc_html_e( 'Redirect URL (optional)', 'mrn-forms' ); ?></span><input type="url" dir="ltr" value="<?php echo esc_attr( $settings['redirectUrl'] ); ?>" data-mrnf-setting="redirectUrl"></label>
				<div class="mrnf-toggle-list"><?php $this->builder_toggle( 'storeEntries', __( 'Store submissions in the inbox', 'mrn-forms' ), $settings['storeEntries'] ); ?><?php $this->builder_toggle( 'emailNotifications', __( 'Send email notifications', 'mrn-forms' ), $settings['emailNotifications'] ); ?></div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the Anar integration settings.
	 *
	 * @param array<string, mixed> $settings Form settings.
	 * @return void
	 */
	private function anar_panel( array $settings ): void {
		$connected = class_exists( \Anar\Login\Integrations\MrnForm::class );
		?>
		<section class="mrnf-panel mrnf-anar-card">
			<div class="mrnf-panel__head">
				<div><span><?php esc_html_e( 'Plugin integration', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Anar and MRN Forms', 'mrn-forms' ); ?></h2></div>
				<strong class="mrnf-anar-card__badge"><?php echo esc_html( $connected ? __( 'Anar available', 'mrn-forms' ) : __( 'Compatible Anar required', 'mrn-forms' ) ); ?></strong>
			</div>
			<p><?php esc_html_e( 'Verify mobile numbers by SMS on regular forms, or build a registration form that creates a WordPress account after verification.', 'mrn-forms' ); ?></p>
			<div class="mrnf-field-grid">
				<label class="mrnf-input"><span><?php esc_html_e( 'How this form uses Anar', 'mrn-forms' ); ?></span>
					<select data-mrnf-setting="anarMode">
						<option value="off" <?php selected( $settings['anarMode'], 'off' ); ?>><?php esc_html_e( 'Off', 'mrn-forms' ); ?></option>
						<option value="sms" <?php selected( $settings['anarMode'], 'sms' ); ?>><?php esc_html_e( 'SMS verification before submission', 'mrn-forms' ); ?></option>
						<option value="registration" <?php selected( $settings['anarMode'], 'registration' ); ?>><?php esc_html_e( 'Anar registration', 'mrn-forms' ); ?></option>
					</select>
				</label>
				<label class="mrnf-input"><span><?php esc_html_e( 'Mobile number field', 'mrn-forms' ); ?></span>
					<select data-mrnf-setting="anarPhoneField"><option value=""><?php esc_html_e( 'Select a phone field', 'mrn-forms' ); ?></option></select>
					<small><?php esc_html_e( 'Use a required phone field with no visibility condition.', 'mrn-forms' ); ?></small>
				</label>
			</div>
			<p><?php esc_html_e( 'For registration, publish this form and select it in Anar settings. Registration answers are saved in this form’s inbox.', 'mrn-forms' ); ?></p>
			<?php if ( $connected ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=anar-login-settings&section=general#anar-mrn-forms' ) ); ?>"><?php esc_html_e( 'Open Anar settings', 'mrn-forms' ); ?> <span aria-hidden="true">↗</span></a>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Notification template editor.
	 *
	 * @param array<int, array<string, mixed>> $notifications Notifications.
	 * @param array<int, array<string, mixed>> $fields Form fields.
	 * @return void
	 */
	private function notifications_panel( array $notifications, array $fields ): void {
		?>
		<div class="mrnf-notifications" data-mrnf-notifications>
			<div class="mrnf-merge-help"><b><?php esc_html_e( 'Merge tags', 'mrn-forms' ); ?></b><code>{form_title}</code><code>{entry_id}</code><code>{site_name}</code><code>{admin_email}</code><code>{all_fields}</code><code>{field:email}</code><small><?php esc_html_e( 'Use each field key, for example {field:phone}.', 'mrn-forms' ); ?></small></div>
			<?php foreach ( $notifications as $index => $notification ) : ?>
				<?php
				$condition = wp_parse_args(
					(array) ( $notification['condition'] ?? array() ),
					array(
						'enabled'  => false,
						'field'    => '',
						'operator' => 'equals',
						'value'    => '',
					)
				);
				?>
				<article class="mrnf-notification" data-notification-index="<?php echo esc_attr( (string) $index ); ?>">
					<header><div><span class="dashicons dashicons-email-alt"></span><input value="<?php echo esc_attr( $notification['name'] ); ?>" data-notification-key="name"></div><label class="mrnf-mini-switch"><input type="checkbox" <?php checked( $notification['enabled'] ); ?> data-notification-key="enabled"><i></i></label></header>
					<div class="mrnf-field-grid"><label class="mrnf-input"><span><?php esc_html_e( 'Recipient', 'mrn-forms' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $notification['to'] ); ?>" data-notification-key="to"></label><label class="mrnf-input"><span><?php esc_html_e( 'Reply-To', 'mrn-forms' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $notification['replyTo'] ); ?>" data-notification-key="replyTo"></label></div>
					<label class="mrnf-input"><span><?php esc_html_e( 'Subject', 'mrn-forms' ); ?></span><input value="<?php echo esc_attr( $notification['subject'] ); ?>" data-notification-key="subject"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'Email body (basic HTML is allowed)', 'mrn-forms' ); ?></span><textarea rows="6" data-notification-key="body"><?php echo esc_textarea( $notification['body'] ); ?></textarea></label>
					<div class="mrnf-notification__routing">
						<label class="mrnf-builder-toggle"><input type="checkbox" <?php checked( $condition['enabled'] ); ?> data-notification-key="condition.enabled"><i></i><span><?php esc_html_e( 'Send this notification conditionally', 'mrn-forms' ); ?></span></label>
						<div class="mrnf-field-grid">
							<label class="mrnf-input"><span><?php esc_html_e( 'Source field', 'mrn-forms' ); ?></span><select data-notification-key="condition.field"><option value=""><?php esc_html_e( 'Select a field…', 'mrn-forms' ); ?></option>
							<?php foreach ( $fields as $field ) : ?>
								<?php if ( ! in_array( $field['type'], array( 'heading', 'html', 'file' ), true ) ) : ?>
									<option value="<?php echo esc_attr( $field['key'] ); ?>" <?php selected( $condition['field'], $field['key'] ); ?>><?php echo esc_html( $field['label'] ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
							</select></label>
							<label class="mrnf-input"><span><?php esc_html_e( 'Condition', 'mrn-forms' ); ?></span><select data-notification-key="condition.operator"><option value="equals" <?php selected( $condition['operator'], 'equals' ); ?>><?php esc_html_e( 'Equals', 'mrn-forms' ); ?></option><option value="not_equals" <?php selected( $condition['operator'], 'not_equals' ); ?>><?php esc_html_e( 'Does not equal', 'mrn-forms' ); ?></option><option value="contains" <?php selected( $condition['operator'], 'contains' ); ?>><?php esc_html_e( 'Contains', 'mrn-forms' ); ?></option><option value="not_empty" <?php selected( $condition['operator'], 'not_empty' ); ?>><?php esc_html_e( 'Is not empty', 'mrn-forms' ); ?></option><option value="empty" <?php selected( $condition['operator'], 'empty' ); ?>><?php esc_html_e( 'Is empty', 'mrn-forms' ); ?></option></select></label>
						</div>
						<label class="mrnf-input"><span><?php esc_html_e( 'Comparison value', 'mrn-forms' ); ?></span><input value="<?php echo esc_attr( $condition['value'] ); ?>" data-notification-key="condition.value"></label>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render one entry details.
	 *
	 * @param int $entry_id Entry ID.
	 * @return void
	 */
	private function render_entry_detail( int $entry_id ): void {
		$entry = $this->entries->find( $entry_id );
		if ( ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'mrn-forms' ) );
		}
		$form        = $this->forms->find( $entry['form_id'] );
		$attribution = (array) ( $entry['attribution'] ?? array() );
		if ( 'unread' === $entry['status'] ) {
			$this->entries->set_status( $entry_id, 'read' );
			$entry['status'] = 'read';
		}
		$labels = $form ? array_column( $form['fields'], 'label', 'key' ) : array();
		$this->header( 'entries', __( 'Entry details', 'mrn-forms' ) );
		?>
		<main class="mrnf-admin__content">
			<div class="mrnf-detail-head"><a class="mrnf-button" href="<?php echo esc_url( $this->page_url( 'entries' ) ); ?>"><?php echo esc_html( is_rtl() ? '→' : '←' ); ?> <?php esc_html_e( 'Back to inbox', 'mrn-forms' ); ?></a><div><?php echo $this->status_badge( $entry['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>#<?php echo esc_html( (string) $entry['id'] ); ?></span></div></div>
			<section class="mrnf-detail-grid">
				<article class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Responses', 'mrn-forms' ); ?></span><h2><?php echo esc_html( $form['title'] ?? __( 'Deleted form', 'mrn-forms' ) ); ?></h2></div></div><dl class="mrnf-entry-values">
				<?php
				foreach ( $entry['values'] as $key => $value ) :
					?>
					<div><dt><?php echo esc_html( $labels[ $key ] ?? $key ); ?></dt><dd><?php $this->entry_value( $value ); ?></dd></div><?php endforeach; ?></dl></article>
				<aside>
					<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'Metadata', 'mrn-forms' ); ?></span><h2><?php esc_html_e( 'Submission details', 'mrn-forms' ); ?></h2></div></div><dl class="mrnf-meta"><div><dt><?php esc_html_e( 'Time', 'mrn-forms' ); ?></dt><dd><?php echo esc_html( get_date_from_gmt( $entry['created_at'], 'Y/m/d H:i' ) ); ?></dd></div><div><dt><?php esc_html_e( 'Traffic source', 'mrn-forms' ); ?></dt><dd><?php echo esc_html( Attribution::label( $attribution ) ); ?></dd></div><div><dt><?php esc_html_e( 'Campaign', 'mrn-forms' ); ?></dt><dd><?php echo esc_html( $attribution['campaign'] ?? '—' ); ?></dd></div><div><dt><?php esc_html_e( 'Landing page', 'mrn-forms' ); ?></dt><dd><?php $this->entry_url( (string) ( $attribution['landing_page'] ?? '' ) ); ?></dd></div><div><dt><?php esc_html_e( 'External referrer', 'mrn-forms' ); ?></dt><dd><?php $this->entry_url( (string) ( $attribution['referrer'] ?? '' ) ); ?></dd></div><div><dt><?php esc_html_e( 'Form submission page', 'mrn-forms' ); ?></dt><dd><?php $this->entry_url( (string) $entry['referer'] ); ?></dd></div><div><dt><?php esc_html_e( 'Browser', 'mrn-forms' ); ?></dt><dd><?php echo esc_html( $entry['user_agent'] ? $entry['user_agent'] : '—' ); ?></dd></div></dl></section>
					<?php
					/**
					 * Render integration panels, such as payment status, beside one entry.
					 *
					 * @param array<string, mixed>      $entry Entry.
					 * @param array<string, mixed>|null $form  Form definition.
					 */
					do_action( 'mrnf_entry_detail_aside', $entry, $form );
					?>
					<section class="mrnf-panel"><div class="mrnf-row-actions mrnf-row-actions--stack"><a href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'unread' ) ); ?>"><?php esc_html_e( 'Mark as unread', 'mrn-forms' ); ?></a><a href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'spam' ) ); ?>"><?php esc_html_e( 'Move to spam', 'mrn-forms' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'delete' ) ); ?>"><?php esc_html_e( 'Delete permanently', 'mrn-forms' ); ?></a></div></section>
				</aside>
			</section>
		</main>
		<?php $this->footer(); ?>
		<?php
	}

	/**
	 * Entry list table.
	 *
	 * @param array<int, array<string, mixed>> $entries Entries.
	 * @param bool                             $compact Compact mode.
	 * @return void
	 */
	private function entries_table( array $entries, bool $compact = false ): void {
		?>
		<div class="mrnf-table-wrap"><table class="mrnf-table"><thead><tr><th><?php esc_html_e( 'Status', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Form', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Response summary', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Traffic source', 'mrn-forms' ); ?></th><th><?php esc_html_e( 'Time', 'mrn-forms' ); ?></th>
		<?php
		if ( ! $compact ) :
			?>
			<th></th><?php endif; ?></tr></thead><tbody>
		<?php
		if ( ! $entries ) :
			?>
			<tr><td colspan="<?php echo esc_attr( $compact ? '5' : '6' ); ?>"><div class="mrnf-empty"><span class="dashicons dashicons-email"></span><b><?php esc_html_e( 'The inbox is empty', 'mrn-forms' ); ?></b><p><?php esc_html_e( 'New submissions will appear here.', 'mrn-forms' ); ?></p></div></td></tr><?php endif; ?>
		<?php
		foreach ( $entries as $entry ) :
			$preview = array_slice( $entry['values'], 0, 2, true );
			?>
			<tr class="<?php echo 'unread' === $entry['status'] ? 'is-unread' : ''; ?>"><td><?php echo $this->status_badge( $entry['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><a class="mrnf-table__title" href="<?php echo esc_url( add_query_arg( 'entry_id', $entry['id'], $this->page_url( 'entries' ) ) ); ?>"><?php echo esc_html( $entry['form_title'] ?? '#' . $entry['form_id'] ); ?></a><small>#<?php echo esc_html( (string) $entry['id'] ); ?></small></td><td><span class="mrnf-entry-preview">
			<?php
			foreach ( $preview as $value ) :
				?>
				<i><?php echo esc_html( wp_trim_words( is_array( $value ) ? implode( __( ', ', 'mrn-forms' ), $value ) : $value, 7 ) ); ?></i><?php endforeach; ?></span></td><td><?php echo esc_html( Attribution::label( (array) ( $entry['attribution'] ?? array() ) ) ); ?></td><td><?php /* translators: %s: elapsed time since the form was updated or the entry was created. */ printf( esc_html__( '%s ago', 'mrn-forms' ), esc_html( human_time_diff( strtotime( $entry['created_at'] . ' UTC' ), time() ) ) ); ?></td>
				<?php
				if ( ! $compact ) :
					?>
				<td><div class="mrnf-row-actions"><a href="<?php echo esc_url( add_query_arg( 'entry_id', $entry['id'], $this->page_url( 'entries' ) ) ); ?>"><?php esc_html_e( 'View', 'mrn-forms' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->entry_action_url( $entry['id'], 'delete' ) ); ?>"><?php esc_html_e( 'Delete', 'mrn-forms' ); ?></a></div></td><?php endif; ?></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	/**
	 * Render the administration header and navigation.
	 *
	 * @param string $active Active tab.
	 * @param string $context Optional context.
	 * @return void
	 */
	private function header( string $active, string $context = '' ): void {
		$tabs = array(
			'dashboard' => array( self::PAGE, __( 'Dashboard', 'mrn-forms' ) ),
			'forms'     => array( self::PAGE . '-forms', __( 'Forms', 'mrn-forms' ) ),
			'builder'   => array( self::PAGE . '-builder', __( 'Form builder', 'mrn-forms' ) ),
			'entries'   => array( self::PAGE . '-entries', __( 'Inbox', 'mrn-forms' ) ),
			'settings'  => array( self::PAGE . '-settings', __( 'Settings', 'mrn-forms' ) ),
		);
		?>
		<div class="wrap mrnf-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<header class="mrnf-admin__hero">
				<div class="mrnf-admin__brand"><span class="mrnf-admin__mark"><img src="<?php echo esc_url( MRNF_URL . 'assets/images/logo.png' ); ?>" width="56" height="56" alt="" aria-hidden="true"></span><div><small>MRN FORMS</small><h1><?php esc_html_e( 'Smart form builder', 'mrn-forms' ); ?></h1><p><?php echo esc_html( $context ? $context : __( 'From the first click to a real conversation with your audience', 'mrn-forms' ) ); ?></p></div></div>
				<div class="mrnf-admin__art" aria-hidden="true"><span></span><span></span><span></span><i></i></div>
				<span class="mrnf-admin__version">v<?php echo esc_html( MRNF_VERSION ); ?></span>
			</header>
			<nav class="mrnf-admin__tabs">
			<?php
			foreach ( $tabs as $key => $tab ) :
				?>
				<a class="<?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab[0] ) ); ?>"><?php echo esc_html( $tab[1] ); ?>
				<?php
				if ( 'entries' === $key && $this->entries->count( 0, 'unread' ) ) :
					?>
				<b><?php echo esc_html( (string) $this->entries->count( 0, 'unread' ) ); ?></b><?php endif; ?></a><?php endforeach; ?></nav>
		<?php
	}

	/**
	 * Close workspace markup.
	 *
	 * @return void
	 */
	private function footer(): void {
		?>
			<footer class="mrnf-admin__footer"><span>MRN Forms <b>v<?php echo esc_html( MRNF_VERSION ); ?></b> · <a href="https://mrnwp.ir/mrnforms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Plugin website', 'mrn-forms' ); ?></a></span><span><a href="https://mrnwp.ir" target="_blank" rel="noopener noreferrer">MRNWP</a> · <a href="https://mehranmarandi.ir" target="_blank" rel="noopener noreferrer">Mehran Marandi</a></span></footer>
		</div>
		<?php
	}

	/**
	 * One dashboard statistic.
	 *
	 * @param string     $label Label.
	 * @param int|string $value Value.
	 * @param string     $help Help text.
	 * @param string     $icon Icon modifier.
	 * @return void
	 */
	private function stat( string $label, int|string $value, string $help, string $icon ): void {
		?>
		<article><i class="mrnf-stat-icon mrnf-stat-icon--<?php echo esc_attr( $icon ); ?>"></i><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( (string) $value ); ?></strong><small><?php echo esc_html( $help ); ?></small></div></article>
		<?php
	}

	/**
	 * Render status pill.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function status_badge( string $status ): string {
		$labels = array(
			'published' => __( 'Published', 'mrn-forms' ),
			'draft'     => __( 'Draft', 'mrn-forms' ),
			'archived'  => __( 'Archived', 'mrn-forms' ),
			'unread'    => __( 'New', 'mrn-forms' ),
			'read'      => __( 'Read', 'mrn-forms' ),
			'spam'      => __( 'Spam', 'mrn-forms' ),
		);
		return '<span class="mrnf-status mrnf-status--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
	}

	/**
	 * Standard setting input.
	 *
	 * @param string     $key Key.
	 * @param string     $label Label.
	 * @param int|string $value Value.
	 * @param string     $type Input type.
	 * @param string     $help Help text.
	 * @return void
	 */
	private function setting_field( string $key, string $label, int|string $value, string $type = 'text', string $help = '' ): void {
		?>
		<label class="mrnf-input"><span><?php echo esc_html( $label ); ?></span><input type="<?php echo esc_attr( $type ); ?>" name="settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
		<?php
		if ( $help ) :
			?>
			<small><?php echo esc_html( $help ); ?></small><?php endif; ?></label>
		<?php
	}

	/**
	 * Settings toggle.
	 *
	 * @param string $key Key.
	 * @param string $title Title.
	 * @param string $description Description.
	 * @param bool   $checked State.
	 * @param bool   $danger Danger style.
	 * @return void
	 */
	private function toggle( string $key, string $title, string $description, bool $checked, bool $danger = false ): void {
		?>
		<label class="mrnf-toggle <?php echo $danger ? 'mrnf-toggle--danger' : ''; ?>"><input type="checkbox" name="settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked ); ?>><i></i><span><b><?php echo esc_html( $title ); ?></b><small><?php echo esc_html( $description ); ?></small></span></label>
		<?php
	}

	/**
	 * Builder JSON toggle.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param bool   $checked State.
	 * @return void
	 */
	private function builder_toggle( string $key, string $label, bool $checked ): void {
		?>
		<label class="mrnf-builder-toggle"><input type="checkbox" data-mrnf-setting="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?>><i></i><span><?php echo esc_html( $label ); ?></span></label>
		<?php
	}

	/**
	 * Render a submission value with safe links.
	 *
	 * @param mixed $value Value.
	 * @return void
	 */
	private function entry_value( mixed $value ): void {
		$value = is_array( $value ) ? implode( __( ', ', 'mrn-forms' ), $value ) : (string) $value;
		if ( is_email( $value ) ) {
			echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';
		} else {
			echo nl2br( esc_html( $value ) );
		}
	}

	/**
	 * Render a safe entry metadata URL or an em dash.
	 *
	 * @param string $url URL to render.
	 * @return void
	 */
	private function entry_url( string $url ): void {
		if ( '' === $url ) {
			echo '—';
			return;
		}
		?>
		<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a>
		<?php
	}

	/**
	 * Resolve a plugin admin page.
	 *
	 * @param string $key Page key.
	 * @return string
	 */
	private function page_url( string $key ): string {
		$slug = 'dashboard' === $key ? self::PAGE : self::PAGE . '-' . $key;
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Protected form action URL.
	 *
	 * @param int    $id Form ID.
	 * @param string $action Action.
	 * @return string
	 */
	private function form_action_url( int $id, string $action ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mrnf_form_action',
					'id'     => $id,
					'do'     => $action,
				),
				admin_url( 'admin-post.php' )
			),
			'mrnf_form_action_' . $id . '_' . $action
		);
	}

	/**
	 * Protected entry action URL.
	 *
	 * @param int    $id Entry ID.
	 * @param string $action Action.
	 * @return string
	 */
	private function entry_action_url( int $id, string $action ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mrnf_entry_action',
					'id'     => $id,
					'do'     => $action,
				),
				admin_url( 'admin-post.php' )
			),
			'mrnf_entry_action_' . $id . '_' . $action
		);
	}

	/**
	 * Ensure administrator access.
	 *
	 * @return void
	 */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this area.', 'mrn-forms' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Store a per-user flash notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Message.
	 * @return void
	 */
	private function set_notice( string $type, string $message ): void {
		set_transient( 'mrnf_admin_notice_' . get_current_user_id(), array( $type, $message ), MINUTE_IN_SECONDS );
	}

	/**
	 * Display and clear the current notice.
	 *
	 * @return void
	 */
	private function notice(): void {
		$notice = get_transient( 'mrnf_admin_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'mrnf_admin_notice_' . get_current_user_id() );
		?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice[0] ? 'error' : 'success' ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php
	}

	/**
	 * Redirect to a plugin page.
	 *
	 * @param string $page Page key.
	 * @return never
	 */
	private function redirect( string $page ): never {
		wp_safe_redirect( $this->page_url( $page ) );
		exit;
	}
}
