<?php
/**
 * Polished administration workspace.
 *
 * @package MRN_Form
 */

namespace MRN\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Provide the branded builder, inbox, reporting, and settings screens.
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
		add_menu_page( __( 'MRN Form', 'mrn-form' ), __( 'فرم‌ها', 'mrn-form' ), $capability, self::PAGE, array( $this, 'render_dashboard' ), 'dashicons-feedback', 21 );
		add_submenu_page( self::PAGE, __( 'داشبورد فرم‌ها', 'mrn-form' ), __( 'داشبورد', 'mrn-form' ), $capability, self::PAGE, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::PAGE, __( 'همه فرم‌ها', 'mrn-form' ), __( 'همه فرم‌ها', 'mrn-form' ), $capability, self::PAGE . '-forms', array( $this, 'render_forms' ) );
		add_submenu_page( self::PAGE, __( 'فرم جدید', 'mrn-form' ), __( 'فرم جدید', 'mrn-form' ), $capability, self::PAGE . '-builder', array( $this, 'render_builder' ) );
		add_submenu_page( self::PAGE, __( 'صندوق ورودی', 'mrn-form' ), __( 'صندوق ورودی', 'mrn-form' ), $capability, self::PAGE . '-entries', array( $this, 'render_entries' ) );
		add_submenu_page( self::PAGE, __( 'تنظیمات MRN Form', 'mrn-form' ), __( 'تنظیمات', 'mrn-form' ), $capability, self::PAGE . '-settings', array( $this, 'render_settings' ) );
	}

	/**
	 * Load assets only in this plugin workspace.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
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
				'i18n'         => array(
					'selectField' => __( 'برای ویرایش تنظیمات، یک فیلد را انتخاب کنید.', 'mrn-form' ),
					'deleteField' => __( 'این فیلد حذف شود؟', 'mrn-form' ),
					'emptyCanvas' => __( 'فیلدها را از ستون کناری بکشید یا روی آن‌ها کلیک کنید.', 'mrn-form' ),
					'choice'      => __( 'گزینه', 'mrn-form' ),
					'unsaved'     => __( 'تغییرات ذخیره‌نشده دارید.', 'mrn-form' ),
					'copySuccess' => __( 'کپی شد', 'mrn-form' ),
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'داشبورد', 'mrn-form' ) . '</a>' );
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
				$this->stat( __( 'کل فرم‌ها', 'mrn-form' ), $forms, sprintf( __( '%d فرم منتشرشده', 'mrn-form' ), $published ), 'forms' );
				?>
				<?php $this->stat( __( 'کل ارسال‌ها', 'mrn-form' ), $entries, __( 'آرشیو امن و قابل خروجی', 'mrn-form' ), 'entries' ); ?>
				<?php $this->stat( __( 'خوانده‌نشده', 'mrn-form' ), $unread, __( 'نیازمند بررسی شما', 'mrn-form' ), 'unread' ); ?>
				<?php $this->stat( __( 'اعلان ایمیلی', 'mrn-form' ), __( 'فعال', 'mrn-form' ), __( 'مدیر و تکمیل‌کننده فرم', 'mrn-form' ), 'mail' ); ?>
			</section>
			<section class="mrnf-dashboard-grid">
				<article class="mrnf-panel">
					<div class="mrnf-panel__head">
						<div><span><?php esc_html_e( 'فعالیت اخیر', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'آخرین ارسال‌ها', 'mrn-form' ); ?></h2></div>
						<a class="mrnf-button" href="<?php echo esc_url( $this->page_url( 'entries' ) ); ?>"><?php esc_html_e( 'مشاهده صندوق ورودی', 'mrn-form' ); ?></a>
					</div>
					<?php $this->entries_table( $latest, true ); ?>
				</article>
				<aside class="mrnf-panel mrnf-quick">
					<span class="mrnf-kicker"><?php esc_html_e( 'شروع سریع', 'mrn-form' ); ?></span>
					<h2><?php esc_html_e( 'یک تجربه روان برای دریافت اطلاعات', 'mrn-form' ); ?></h2>
					<p><?php esc_html_e( 'فرم را بسازید، ظاهرش را با قالب هماهنگ کنید و با شورت‌کد یا بلوک MRN Form نمایش دهید.', 'mrn-form' ); ?></p>
					<a class="mrnf-button mrnf-button--primary mrnf-button--large" href="<?php echo esc_url( $this->page_url( 'builder' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'ساخت فرم جدید', 'mrn-form' ); ?></a>
					<div class="mrnf-health">
						<div class="<?php echo $published ? 'is-ok' : ''; ?>"><i></i><span><b><?php esc_html_e( 'فرم منتشرشده', 'mrn-form' ); ?></b><small>
						<?php
						/* translators: %d: number of forms ready to collect entries. */
						echo $published ? esc_html( sprintf( __( '%d فرم آماده دریافت', 'mrn-form' ), $published ) ) : esc_html__( 'هنوز فرمی منتشر نشده', 'mrn-form' );
						?>
						</small></span></div>
						<div class="is-ok"><i></i><span><b><?php esc_html_e( 'ذخیره‌سازی اختصاصی', 'mrn-form' ); ?></b><small><?php esc_html_e( 'جدول‌های بهینه و مستقل', 'mrn-form' ); ?></small></span></div>
						<div class="is-ok"><i></i><span><b><?php esc_html_e( 'محافظت ضداسپم', 'mrn-form' ); ?></b><small><?php esc_html_e( 'Honeypot و محدودسازی نرخ', 'mrn-form' ); ?></small></span></div>
					</div>
				</aside>
			</section>
			<?php if ( $active_forms ) : ?>
				<section class="mrnf-panel">
					<div class="mrnf-panel__head"><div><span><?php esc_html_e( 'فرم‌های آنلاین', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'دسترسی سریع و کد نمایش', 'mrn-form' ); ?></h2></div></div>
					<div class="mrnf-shortcode-grid">
						<?php foreach ( $active_forms as $form ) : ?>
							<article><div><b><?php echo esc_html( $form['title'] ); ?></b><small>
							<?php
							/* translators: %d: number of entries for a form. */
							echo esc_html( sprintf( __( '%d ارسال', 'mrn-form' ), $this->entries->count( $form['id'] ) ) );
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
					<div><span><?php esc_html_e( 'کتابخانه', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'همه فرم‌ها', 'mrn-form' ); ?></h2><p><?php esc_html_e( 'ساخت، ویرایش، تکثیر و مدیریت فرم‌ها از یک فضای یکپارچه.', 'mrn-form' ); ?></p></div>
					<a class="mrnf-button mrnf-button--primary" href="<?php echo esc_url( $this->page_url( 'builder' ) ); ?>"><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'فرم جدید', 'mrn-form' ); ?></a>
				</div>
				<form class="mrnf-search" method="get"><input type="hidden" name="page" value="mrn-form-forms"><input name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'جست‌وجوی فرم…', 'mrn-form' ); ?>"><button class="mrnf-button" type="submit"><?php esc_html_e( 'جست‌وجو', 'mrn-form' ); ?></button></form>
				<div class="mrnf-table-wrap">
					<table class="mrnf-table">
						<thead><tr><th><?php esc_html_e( 'فرم', 'mrn-form' ); ?></th><th><?php esc_html_e( 'وضعیت', 'mrn-form' ); ?></th><th><?php esc_html_e( 'ارسال‌ها', 'mrn-form' ); ?></th><th><?php esc_html_e( 'کد نمایش', 'mrn-form' ); ?></th><th><?php esc_html_e( 'آخرین تغییر', 'mrn-form' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						if ( ! $forms ) :
							?>
							<tr><td colspan="6"><div class="mrnf-empty"><span class="dashicons dashicons-feedback"></span><b><?php esc_html_e( 'هنوز فرمی ندارید', 'mrn-form' ); ?></b><p><?php esc_html_e( 'اولین فرم را در چند دقیقه بسازید.', 'mrn-form' ); ?></p></div></td></tr><?php endif; ?>
						<?php foreach ( $forms as $form ) : ?>
							<tr>
								<td><a class="mrnf-table__title" href="<?php echo esc_url( add_query_arg( 'id', $form['id'], $this->page_url( 'builder' ) ) ); ?>"><?php echo esc_html( $form['title'] ); ?></a><small>#<?php echo esc_html( (string) $form['id'] ); ?> · <?php echo esc_html( $form['slug'] ); ?></small></td>
								<td><?php echo $this->status_badge( $form['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><a href="<?php echo esc_url( add_query_arg( 'form_id', $form['id'], $this->page_url( 'entries' ) ) ); ?>"><?php echo esc_html( (string) $this->entries->count( $form['id'] ) ); ?></a></td>
								<td><code>[mrn_form id="<?php echo esc_html( (string) $form['id'] ); ?>"]</code></td>
								<td><?php echo esc_html( human_time_diff( strtotime( $form['updated_at'] . ' UTC' ), time() ) ); ?> <?php esc_html_e( 'پیش', 'mrn-form' ); ?></td>
								<td><div class="mrnf-row-actions"><a href="<?php echo esc_url( add_query_arg( 'id', $form['id'], $this->page_url( 'builder' ) ) ); ?>"><?php esc_html_e( 'ویرایش', 'mrn-form' ); ?></a><a href="<?php echo esc_url( $this->form_action_url( $form['id'], 'duplicate' ) ); ?>"><?php esc_html_e( 'تکثیر', 'mrn-form' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->form_action_url( $form['id'], 'delete' ) ); ?>"><?php esc_html_e( 'حذف', 'mrn-form' ); ?></a></div></td>
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
				'title'         => __( 'فرم تماس جدید', 'mrn-form' ),
				'slug'          => '',
				'status'        => 'draft',
				'description'   => __( 'لطفاً فرم زیر را تکمیل کنید؛ در اولین فرصت با شما تماس می‌گیریم.', 'mrn-form' ),
				'fields'        => Field_Registry::starter_fields(),
				'settings'      => Form_Repository::default_settings(),
				'notifications' => Form_Repository::default_notifications(),
			);
		}
		$this->header( 'builder', $form['id'] ? __( 'ویرایش فرم', 'mrn-form' ) : __( 'فرم تازه', 'mrn-form' ) );
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
					<div class="mrnf-builder__identity"><input name="title" value="<?php echo esc_attr( $form['title'] ); ?>" aria-label="<?php esc_attr_e( 'عنوان فرم', 'mrn-form' ); ?>" required><span>#<?php echo $form['id'] ? esc_html( (string) $form['id'] ) : esc_html__( 'جدید', 'mrn-form' ); ?></span></div>
					<div class="mrnf-builder__actions">
						<select name="status" aria-label="<?php esc_attr_e( 'وضعیت فرم', 'mrn-form' ); ?>"><option value="draft" <?php selected( $form['status'], 'draft' ); ?>><?php esc_html_e( 'پیش‌نویس', 'mrn-form' ); ?></option><option value="published" <?php selected( $form['status'], 'published' ); ?>><?php esc_html_e( 'منتشرشده', 'mrn-form' ); ?></option><option value="archived" <?php selected( $form['status'], 'archived' ); ?>><?php esc_html_e( 'بایگانی', 'mrn-form' ); ?></option></select>
						<?php
						if ( $form['id'] ) :
							?>
							<button type="button" class="mrnf-button" data-mrnf-preview><?php esc_html_e( 'پیش‌نمایش', 'mrn-form' ); ?></button><?php endif; ?>
						<button type="submit" class="mrnf-button mrnf-button--primary"><?php esc_html_e( 'ذخیره فرم', 'mrn-form' ); ?></button>
					</div>
				</div>
				<div class="mrnf-builder__workspace">
					<aside class="mrnf-builder__palette">
						<div class="mrnf-builder__panel-title"><span><?php esc_html_e( 'فیلدها', 'mrn-form' ); ?></span><small><?php esc_html_e( 'برای افزودن کلیک یا Drag کنید', 'mrn-form' ); ?></small></div>
						<div class="mrnf-palette">
							<?php foreach ( Field_Registry::all() as $type => $definition ) : ?>
								<button type="button" draggable="true" data-mrnf-add-field="<?php echo esc_attr( $type ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $definition['icon'] ); ?>"></span><b><?php echo esc_html( $definition['label'] ); ?></b></button>
							<?php endforeach; ?>
						</div>
						<div class="mrnf-builder__tips"><b><?php esc_html_e( 'نمایش در سایت', 'mrn-form' ); ?></b><code>[mrn_form id="<?php echo $form['id'] ? esc_html( (string) $form['id'] ) : 'ID'; ?>"]</code><small><?php esc_html_e( 'یا از بلوک MRN Form و تابع mrn_form() استفاده کنید.', 'mrn-form' ); ?></small></div>
					</aside>
					<section class="mrnf-builder__stage">
						<div class="mrnf-builder__tabs"><button type="button" class="is-active" data-mrnf-tab="fields"><?php esc_html_e( 'ساختار', 'mrn-form' ); ?></button><button type="button" data-mrnf-tab="appearance"><?php esc_html_e( 'ظاهر و رفتار', 'mrn-form' ); ?></button><button type="button" data-mrnf-tab="notifications"><?php esc_html_e( 'اعلان‌ها', 'mrn-form' ); ?></button></div>
						<div class="mrnf-builder__tab is-active" data-mrnf-tab-panel="fields">
							<div class="mrnf-canvas-head"><div><input name="description" value="<?php echo esc_attr( $form['description'] ); ?>" placeholder="<?php esc_attr_e( 'توضیح کوتاه فرم…', 'mrn-form' ); ?>"></div><span data-mrnf-field-count></span></div>
							<div class="mrnf-canvas" data-mrnf-canvas></div>
						</div>
						<div class="mrnf-builder__tab" data-mrnf-tab-panel="appearance">
							<?php $this->appearance_panel( $form['settings'] ); ?>
						</div>
						<div class="mrnf-builder__tab" data-mrnf-tab-panel="notifications">
								<?php $this->notifications_panel( $form['notifications'], $form['fields'] ); ?>
						</div>
					</section>
					<aside class="mrnf-builder__inspector">
						<div class="mrnf-builder__panel-title"><span><?php esc_html_e( 'تنظیمات فیلد', 'mrn-form' ); ?></span><small><?php esc_html_e( 'تغییرات همان لحظه اعمال می‌شود', 'mrn-form' ); ?></small></div>
						<div data-mrnf-inspector><div class="mrnf-inspector-empty"><span class="dashicons dashicons-admin-generic"></span><p><?php esc_html_e( 'یک فیلد را برای تنظیم انتخاب کنید.', 'mrn-form' ); ?></p></div></div>
					</aside>
				</div>
			</form>
			<div class="mrnf-preview-modal" data-mrnf-preview-modal hidden><div><button type="button" data-mrnf-close-preview>×</button><iframe title="<?php esc_attr_e( 'پیش‌نمایش فرم', 'mrn-form' ); ?>"></iframe></div></div>
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
				<div class="mrnf-panel__head"><div><span><?php esc_html_e( 'ارسال‌های ثبت‌شده', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'صندوق ورودی', 'mrn-form' ); ?></h2><p><?php esc_html_e( 'بررسی، دسته‌بندی و خروجی گرفتن از پاسخ‌ها.', 'mrn-form' ); ?></p></div><a class="mrnf-button" href="
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
															"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'خروجی CSV', 'mrn-form' ); ?></a></div>
				<form class="mrnf-filters" method="get"><input type="hidden" name="page" value="mrn-form-entries"><select name="form_id"><option value="0"><?php esc_html_e( 'همه فرم‌ها', 'mrn-form' ); ?></option>
				<?php
				foreach ( $forms as $form ) :
					?>
					<option value="<?php echo esc_attr( (string) $form['id'] ); ?>" <?php selected( $form_id, $form['id'] ); ?>><?php echo esc_html( $form['title'] ); ?></option><?php endforeach; ?></select><select name="status"><option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'mrn-form' ); ?></option><option value="unread" <?php selected( $status, 'unread' ); ?>><?php esc_html_e( 'خوانده‌نشده', 'mrn-form' ); ?></option><option value="read" <?php selected( $status, 'read' ); ?>><?php esc_html_e( 'خوانده‌شده', 'mrn-form' ); ?></option><option value="spam" <?php selected( $status, 'spam' ); ?>><?php esc_html_e( 'هرزنامه', 'mrn-form' ); ?></option></select><button class="mrnf-button" type="submit"><?php esc_html_e( 'اعمال فیلتر', 'mrn-form' ); ?></button></form>
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
				<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'هویت ایمیل', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'فرستنده و برند', 'mrn-form' ); ?></h2><p><?php esc_html_e( 'مشخصات فرستنده و رنگ‌های قالب HTML همه ایمیل‌ها.', 'mrn-form' ); ?></p></div></div><div class="mrnf-field-grid">
					<?php $this->setting_field( 'brand_name', __( 'نام برند', 'mrn-form' ), $settings['brand_name'] ); ?>
					<?php $this->setting_field( 'email_logo', __( 'نشانی لوگو', 'mrn-form' ), $settings['email_logo'], 'url', __( 'تصویر مربع با HTTPS پیشنهاد می‌شود.', 'mrn-form' ) ); ?>
					<?php $this->setting_field( 'from_name', __( 'نام فرستنده', 'mrn-form' ), $settings['from_name'] ); ?>
					<?php $this->setting_field( 'from_email', __( 'ایمیل فرستنده', 'mrn-form' ), $settings['from_email'], 'email' ); ?>
					<?php $this->setting_field( 'admin_email', __( 'ایمیل پیش‌فرض مدیر', 'mrn-form' ), $settings['admin_email'], 'email', __( 'در merge tag با {admin_email} قابل استفاده است.', 'mrn-form' ) ); ?>
				</div><div class="mrnf-color-row"><label><span><?php esc_html_e( 'رنگ اصلی', 'mrn-form' ); ?></span><input type="color" name="settings[primary_color]" value="<?php echo esc_attr( $settings['primary_color'] ); ?>"></label><label><span><?php esc_html_e( 'رنگ تأکیدی', 'mrn-form' ); ?></span><input type="color" name="settings[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>"></label></div></section>
				<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'امنیت و داده', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'کنترل ارسال و حذف اطلاعات', 'mrn-form' ); ?></h2></div></div><div class="mrnf-field-grid"><?php $this->setting_field( 'rate_limit', __( 'حداکثر ارسال در ۱۰ دقیقه', 'mrn-form' ), $settings['rate_limit'], 'number', __( 'برای هر IP و هر فرم؛ بین ۱ تا ۵۰.', 'mrn-form' ) ); ?></div><?php $this->toggle( 'delete_on_uninstall', __( 'حذف کامل داده‌ها هنگام uninstall', 'mrn-form' ), __( 'فرم‌ها، ارسال‌ها، لاگ ایمیل و تنظیمات برای همیشه حذف می‌شوند.', 'mrn-form' ), (bool) $settings['delete_on_uninstall'], true ); ?></section>
				<div class="mrnf-settings__save"><button class="mrnf-button mrnf-button--primary mrnf-button--large" type="submit"><?php esc_html_e( 'ذخیره تنظیمات', 'mrn-form' ); ?></button></div>
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
		$this->set_notice( 'success', __( 'فرم با موفقیت ذخیره شد.', 'mrn-form' ) );
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
			$this->set_notice( 'success', __( 'فرم و ارسال‌های مرتبط حذف شدند.', 'mrn-form' ) );
		} elseif ( 'duplicate' === $action ) {
			$result = $this->forms->duplicate( $id );
			$this->set_notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'یک نسخه پیش‌نویس ساخته شد.', 'mrn-form' ) );
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
			$this->set_notice( 'success', __( 'ارسال حذف شد.', 'mrn-form' ) );
		} elseif ( in_array( $action, array( 'read', 'unread', 'spam' ), true ) ) {
			$this->entries->set_status( $id, $action );
			$this->set_notice( 'success', __( 'وضعیت ارسال به‌روز شد.', 'mrn-form' ) );
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
		fputcsv( $output, array_merge( array( 'ID', 'Form', 'Status', 'Created' ), $keys ), ',', '"', '' );
		foreach ( $entries as $entry ) {
			$row = array( $entry['id'], $entry['form_title'], $entry['status'], $entry['created_at'] );
			foreach ( $keys as $key ) {
				$value = $entry['values'][ $key ] ?? '';
				$row[] = is_array( $value ) ? implode( ' | ', $value ) : $value;
			}
			fputcsv( $output, $row, ',', '"', '' );
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
		$this->set_notice( 'success', __( 'تنظیمات ذخیره شد.', 'mrn-form' ) );
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
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-admin-appearance"></span><div><b><?php esc_html_e( 'ظاهر فرم', 'mrn-form' ); ?></b><small><?php esc_html_e( 'هماهنگ با هر قالب MRN و قابل Override با CSS', 'mrn-form' ); ?></small></div></div>
				<div class="mrnf-field-grid">
					<label class="mrnf-input"><span><?php esc_html_e( 'رنگ اصلی', 'mrn-form' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['primaryColor'] ); ?>" data-mrnf-setting="primaryColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'رنگ تأکیدی', 'mrn-form' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['accentColor'] ); ?>" data-mrnf-setting="accentColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'پس‌زمینه', 'mrn-form' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['backgroundColor'] ); ?>" data-mrnf-setting="backgroundColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'رنگ متن', 'mrn-form' ); ?></span><input type="color" value="<?php echo esc_attr( $settings['textColor'] ); ?>" data-mrnf-setting="textColor"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'فاصله فیلدها (px)', 'mrn-form' ); ?></span><input type="number" min="0" max="48" value="<?php echo esc_attr( (string) $settings['layoutGap'] ); ?>" data-mrnf-setting="layoutGap"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'گردی گوشه‌ها (px)', 'mrn-form' ); ?></span><input type="number" min="0" max="40" value="<?php echo esc_attr( (string) $settings['borderRadius'] ); ?>" data-mrnf-setting="borderRadius"></label>
				</div>
			</section>
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-editor-alignleft"></span><div><b><?php esc_html_e( 'چیدمان و متن‌ها', 'mrn-form' ); ?></b></div></div>
				<div class="mrnf-field-grid">
					<label class="mrnf-input"><span><?php esc_html_e( 'متن دکمه', 'mrn-form' ); ?></span><input value="<?php echo esc_attr( $settings['submitLabel'] ); ?>" data-mrnf-setting="submitLabel"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'جایگاه برچسب', 'mrn-form' ); ?></span><select data-mrnf-setting="labelPosition"><option value="top" <?php selected( $settings['labelPosition'], 'top' ); ?>><?php esc_html_e( 'بالای فیلد', 'mrn-form' ); ?></option><option value="inline" <?php selected( $settings['labelPosition'], 'inline' ); ?>><?php esc_html_e( 'کنار فیلد', 'mrn-form' ); ?></option><option value="hidden" <?php selected( $settings['labelPosition'], 'hidden' ); ?>><?php esc_html_e( 'مخفی', 'mrn-form' ); ?></option></select></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'جهت', 'mrn-form' ); ?></span><select data-mrnf-setting="direction"><option value="auto" <?php selected( $settings['direction'], 'auto' ); ?>><?php esc_html_e( 'خودکار', 'mrn-form' ); ?></option><option value="rtl" <?php selected( $settings['direction'], 'rtl' ); ?>>RTL</option><option value="ltr" <?php selected( $settings['direction'], 'ltr' ); ?>>LTR</option></select></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'کلاس CSS سفارشی', 'mrn-form' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $settings['customClass'] ); ?>" data-mrnf-setting="customClass"></label>
				</div>
				<div class="mrnf-toggle-list">
					<?php $this->builder_toggle( 'showTitle', __( 'نمایش عنوان فرم', 'mrn-form' ), $settings['showTitle'] ); ?>
					<?php $this->builder_toggle( 'showDescription', __( 'نمایش توضیحات فرم', 'mrn-form' ), $settings['showDescription'] ); ?>
					<?php $this->builder_toggle( 'buttonFullWidth', __( 'دکمه تمام‌عرض', 'mrn-form' ), $settings['buttonFullWidth'] ); ?>
				</div>
			</section>
			<section class="mrnf-config-card"><div class="mrnf-config-card__head"><span class="dashicons dashicons-yes-alt"></span><div><b><?php esc_html_e( 'پس از ارسال', 'mrn-form' ); ?></b></div></div>
				<label class="mrnf-input"><span><?php esc_html_e( 'پیام موفقیت', 'mrn-form' ); ?></span><textarea data-mrnf-setting="successMessage"><?php echo esc_textarea( $settings['successMessage'] ); ?></textarea></label>
				<label class="mrnf-input"><span><?php esc_html_e( 'پیام خطا', 'mrn-form' ); ?></span><textarea data-mrnf-setting="errorMessage"><?php echo esc_textarea( $settings['errorMessage'] ); ?></textarea></label>
				<label class="mrnf-input"><span><?php esc_html_e( 'انتقال به نشانی (اختیاری)', 'mrn-form' ); ?></span><input type="url" dir="ltr" value="<?php echo esc_attr( $settings['redirectUrl'] ); ?>" data-mrnf-setting="redirectUrl"></label>
				<div class="mrnf-toggle-list"><?php $this->builder_toggle( 'storeEntries', __( 'ذخیره ارسال‌ها در صندوق ورودی', 'mrn-form' ), $settings['storeEntries'] ); ?><?php $this->builder_toggle( 'emailNotifications', __( 'ارسال اعلان‌های ایمیلی', 'mrn-form' ), $settings['emailNotifications'] ); ?></div>
			</section>
		</div>
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
			<div class="mrnf-merge-help"><b><?php esc_html_e( 'Merge Tagها', 'mrn-form' ); ?></b><code>{form_title}</code><code>{entry_id}</code><code>{site_name}</code><code>{admin_email}</code><code>{all_fields}</code><code>{field:email}</code><small><?php esc_html_e( 'برای هر فیلد از کلید آن استفاده کنید؛ مانند {field:phone}.', 'mrn-form' ); ?></small></div>
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
					<div class="mrnf-field-grid"><label class="mrnf-input"><span><?php esc_html_e( 'گیرنده', 'mrn-form' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $notification['to'] ); ?>" data-notification-key="to"></label><label class="mrnf-input"><span><?php esc_html_e( 'Reply-To', 'mrn-form' ); ?></span><input dir="ltr" value="<?php echo esc_attr( $notification['replyTo'] ); ?>" data-notification-key="replyTo"></label></div>
					<label class="mrnf-input"><span><?php esc_html_e( 'موضوع', 'mrn-form' ); ?></span><input value="<?php echo esc_attr( $notification['subject'] ); ?>" data-notification-key="subject"></label>
					<label class="mrnf-input"><span><?php esc_html_e( 'متن ایمیل (HTML ساده مجاز است)', 'mrn-form' ); ?></span><textarea rows="6" data-notification-key="body"><?php echo esc_textarea( $notification['body'] ); ?></textarea></label>
					<div class="mrnf-notification__routing">
						<label class="mrnf-builder-toggle"><input type="checkbox" <?php checked( $condition['enabled'] ); ?> data-notification-key="condition.enabled"><i></i><span><?php esc_html_e( 'ارسال شرطی این اعلان', 'mrn-form' ); ?></span></label>
						<div class="mrnf-field-grid">
							<label class="mrnf-input"><span><?php esc_html_e( 'فیلد مبنا', 'mrn-form' ); ?></span><select data-notification-key="condition.field"><option value=""><?php esc_html_e( 'انتخاب فیلد…', 'mrn-form' ); ?></option>
							<?php foreach ( $fields as $field ) : ?>
								<?php if ( ! in_array( $field['type'], array( 'heading', 'html', 'file' ), true ) ) : ?>
									<option value="<?php echo esc_attr( $field['key'] ); ?>" <?php selected( $condition['field'], $field['key'] ); ?>><?php echo esc_html( $field['label'] ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
							</select></label>
							<label class="mrnf-input"><span><?php esc_html_e( 'شرط', 'mrn-form' ); ?></span><select data-notification-key="condition.operator"><option value="equals" <?php selected( $condition['operator'], 'equals' ); ?>><?php esc_html_e( 'برابر باشد با', 'mrn-form' ); ?></option><option value="not_equals" <?php selected( $condition['operator'], 'not_equals' ); ?>><?php esc_html_e( 'برابر نباشد با', 'mrn-form' ); ?></option><option value="contains" <?php selected( $condition['operator'], 'contains' ); ?>><?php esc_html_e( 'شامل باشد', 'mrn-form' ); ?></option><option value="not_empty" <?php selected( $condition['operator'], 'not_empty' ); ?>><?php esc_html_e( 'خالی نباشد', 'mrn-form' ); ?></option><option value="empty" <?php selected( $condition['operator'], 'empty' ); ?>><?php esc_html_e( 'خالی باشد', 'mrn-form' ); ?></option></select></label>
						</div>
						<label class="mrnf-input"><span><?php esc_html_e( 'مقدار مقایسه', 'mrn-form' ); ?></span><input value="<?php echo esc_attr( $condition['value'] ); ?>" data-notification-key="condition.value"></label>
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
			wp_die( esc_html__( 'ارسال پیدا نشد.', 'mrn-form' ) );
		}
		$form = $this->forms->find( $entry['form_id'] );
		if ( 'unread' === $entry['status'] ) {
			$this->entries->set_status( $entry_id, 'read' );
			$entry['status'] = 'read';
		}
		$labels = $form ? array_column( $form['fields'], 'label', 'key' ) : array();
		$this->header( 'entries', __( 'جزئیات ارسال', 'mrn-form' ) );
		?>
		<main class="mrnf-admin__content">
			<div class="mrnf-detail-head"><a class="mrnf-button" href="<?php echo esc_url( $this->page_url( 'entries' ) ); ?>">→ <?php esc_html_e( 'بازگشت به صندوق ورودی', 'mrn-form' ); ?></a><div><?php echo $this->status_badge( $entry['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span>#<?php echo esc_html( (string) $entry['id'] ); ?></span></div></div>
			<section class="mrnf-detail-grid">
				<article class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'پاسخ‌ها', 'mrn-form' ); ?></span><h2><?php echo esc_html( $form['title'] ?? __( 'فرم حذف‌شده', 'mrn-form' ) ); ?></h2></div></div><dl class="mrnf-entry-values">
				<?php
				foreach ( $entry['values'] as $key => $value ) :
					?>
					<div><dt><?php echo esc_html( $labels[ $key ] ?? $key ); ?></dt><dd><?php $this->entry_value( $value ); ?></dd></div><?php endforeach; ?></dl></article>
				<aside>
					<section class="mrnf-panel"><div class="mrnf-panel__head"><div><span><?php esc_html_e( 'مشخصات', 'mrn-form' ); ?></span><h2><?php esc_html_e( 'اطلاعات ثبت', 'mrn-form' ); ?></h2></div></div><dl class="mrnf-meta"><div><dt><?php esc_html_e( 'زمان', 'mrn-form' ); ?></dt><dd><?php echo esc_html( get_date_from_gmt( $entry['created_at'], 'Y/m/d H:i' ) ); ?></dd></div><div><dt><?php esc_html_e( 'صفحه مبدأ', 'mrn-form' ); ?></dt><dd><a href="<?php echo esc_url( $entry['referer'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $entry['referer'] ? $entry['referer'] : '—' ); ?></a></dd></div><div><dt><?php esc_html_e( 'مرورگر', 'mrn-form' ); ?></dt><dd><?php echo esc_html( $entry['user_agent'] ? $entry['user_agent'] : '—' ); ?></dd></div></dl></section>
					<section class="mrnf-panel"><div class="mrnf-row-actions mrnf-row-actions--stack"><a href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'unread' ) ); ?>"><?php esc_html_e( 'علامت‌گذاری خوانده‌نشده', 'mrn-form' ); ?></a><a href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'spam' ) ); ?>"><?php esc_html_e( 'انتقال به هرزنامه', 'mrn-form' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->entry_action_url( $entry_id, 'delete' ) ); ?>"><?php esc_html_e( 'حذف دائمی', 'mrn-form' ); ?></a></div></section>
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
		<div class="mrnf-table-wrap"><table class="mrnf-table"><thead><tr><th><?php esc_html_e( 'وضعیت', 'mrn-form' ); ?></th><th><?php esc_html_e( 'فرم', 'mrn-form' ); ?></th><th><?php esc_html_e( 'خلاصه پاسخ', 'mrn-form' ); ?></th><th><?php esc_html_e( 'زمان', 'mrn-form' ); ?></th>
		<?php
		if ( ! $compact ) :
			?>
			<th></th><?php endif; ?></tr></thead><tbody>
		<?php
		if ( ! $entries ) :
			?>
			<tr><td colspan="5"><div class="mrnf-empty"><span class="dashicons dashicons-email"></span><b><?php esc_html_e( 'صندوق ورودی خالی است', 'mrn-form' ); ?></b><p><?php esc_html_e( 'ارسال‌های جدید اینجا نمایش داده می‌شوند.', 'mrn-form' ); ?></p></div></td></tr><?php endif; ?>
		<?php
		foreach ( $entries as $entry ) :
			$preview = array_slice( $entry['values'], 0, 2, true );
			?>
			<tr class="<?php echo 'unread' === $entry['status'] ? 'is-unread' : ''; ?>"><td><?php echo $this->status_badge( $entry['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><a class="mrnf-table__title" href="<?php echo esc_url( add_query_arg( 'entry_id', $entry['id'], $this->page_url( 'entries' ) ) ); ?>"><?php echo esc_html( $entry['form_title'] ?? '#' . $entry['form_id'] ); ?></a><small>#<?php echo esc_html( (string) $entry['id'] ); ?></small></td><td><span class="mrnf-entry-preview">
			<?php
			foreach ( $preview as $value ) :
				?>
				<i><?php echo esc_html( wp_trim_words( is_array( $value ) ? implode( '، ', $value ) : $value, 7 ) ); ?></i><?php endforeach; ?></span></td><td><?php echo esc_html( human_time_diff( strtotime( $entry['created_at'] . ' UTC' ), time() ) ); ?> <?php esc_html_e( 'پیش', 'mrn-form' ); ?></td>
				<?php
				if ( ! $compact ) :
					?>
				<td><div class="mrnf-row-actions"><a href="<?php echo esc_url( add_query_arg( 'entry_id', $entry['id'], $this->page_url( 'entries' ) ) ); ?>"><?php esc_html_e( 'مشاهده', 'mrn-form' ); ?></a><a class="is-danger" data-mrnf-confirm href="<?php echo esc_url( $this->entry_action_url( $entry['id'], 'delete' ) ); ?>"><?php esc_html_e( 'حذف', 'mrn-form' ); ?></a></div></td><?php endif; ?></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	/**
	 * Branded workspace header.
	 *
	 * @param string $active Active tab.
	 * @param string $context Optional context.
	 * @return void
	 */
	private function header( string $active, string $context = '' ): void {
		$tabs = array(
			'dashboard' => array( self::PAGE, __( 'داشبورد', 'mrn-form' ) ),
			'forms'     => array( self::PAGE . '-forms', __( 'فرم‌ها', 'mrn-form' ) ),
			'builder'   => array( self::PAGE . '-builder', __( 'فرم‌ساز', 'mrn-form' ) ),
			'entries'   => array( self::PAGE . '-entries', __( 'صندوق ورودی', 'mrn-form' ) ),
			'settings'  => array( self::PAGE . '-settings', __( 'تنظیمات', 'mrn-form' ) ),
		);
		?>
		<div class="wrap mrnf-admin" dir="rtl">
			<header class="mrnf-admin__hero">
				<div class="mrnf-admin__brand"><span class="mrnf-admin__mark"><i></i><i></i><i></i></span><div><small>MRN FORM</small><h1><?php esc_html_e( 'فرم‌ساز هوشمند', 'mrn-form' ); ?></h1><p><?php echo esc_html( $context ? $context : __( 'از اولین کلیک تا یک گفت‌وگوی واقعی با مخاطب', 'mrn-form' ) ); ?></p></div></div>
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
			<footer class="mrnf-admin__footer"><span>MRN Form <b>v<?php echo esc_html( MRNF_VERSION ); ?></b></span><span><?php esc_html_e( 'سبک، مستقل از صفحه‌ساز، امن و سازگار با قالب‌های MRN', 'mrn-form' ); ?></span></footer>
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
			'published' => __( 'منتشرشده', 'mrn-form' ),
			'draft'     => __( 'پیش‌نویس', 'mrn-form' ),
			'archived'  => __( 'بایگانی', 'mrn-form' ),
			'unread'    => __( 'جدید', 'mrn-form' ),
			'read'      => __( 'خوانده‌شده', 'mrn-form' ),
			'spam'      => __( 'هرزنامه', 'mrn-form' ),
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
		$value = is_array( $value ) ? implode( '، ', $value ) : (string) $value;
		if ( is_email( $value ) ) {
			echo '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';
		} else {
			echo nl2br( esc_html( $value ) );
		}
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
			wp_die( esc_html__( 'اجازه دسترسی به این بخش را ندارید.', 'mrn-form' ), '', array( 'response' => 403 ) );
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
