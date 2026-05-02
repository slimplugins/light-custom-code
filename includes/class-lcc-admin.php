<?php
/**
 * Admin panel: menus, pages, and form processing.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Admin
 *
 * @since 1.0.0
 */
class LCC_Admin {

	// ---------------------------------------------------------------------------
	// Constants
	// ---------------------------------------------------------------------------

	const MENU_SLUG          = 'light-custom-code';
	const SUBMENU_SLUG_HF    = 'lcc-head-footer';
	const SUBMENU_SLUG_CSS   = 'lcc-custom-css';
	const SUBMENU_SLUG_INFO  = 'lcc-info';
	const NONCE_SNIPPET_FORM = 'lcc_snippet_form';
	const NONCE_SNIPPET_ACT  = 'lcc_snippet_action';
	const NONCE_HF_FORM      = 'lcc_hf_form';
	const NONCE_CSS_FORM     = 'lcc_css_form';
	const OPTION_SNIPPETS    = 'lcc_snippets';
	const OPTION_HEAD        = 'lcc_head_code';
	const OPTION_FOOTER      = 'lcc_footer_code';
	const OPTION_CSS         = 'lcc_css';
	const MAX_SNIPPETS       = 100;

	// ---------------------------------------------------------------------------
	// Constructor
	// ---------------------------------------------------------------------------

	/**
	 * Register all admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'process_forms' ) );
		add_action( 'admin_init', array( $this, 'process_snippet_action' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu Registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the top-level menu and sub-menu pages.
	 *
	 * @since 1.0.0
	 */
	public function register_menus() {
		add_menu_page(
			esc_html__( 'Light Custom Code', 'light-custom-code' ),
			esc_html__( 'Custom Code', 'light-custom-code' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_snippets_page' ),
			'dashicons-editor-code',
			81
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'PHP Snippets — Light Custom Code', 'light-custom-code' ),
			esc_html__( 'PHP Snippets', 'light-custom-code' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_snippets_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Head & Footer — Light Custom Code', 'light-custom-code' ),
			esc_html__( 'Head &amp; Footer', 'light-custom-code' ),
			'manage_options',
			self::SUBMENU_SLUG_HF,
			array( $this, 'render_head_footer_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Custom CSS — Light Custom Code', 'light-custom-code' ),
			esc_html__( 'Custom CSS', 'light-custom-code' ),
			'manage_options',
			self::SUBMENU_SLUG_CSS,
			array( $this, 'render_css_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Info — Light Custom Code', 'light-custom-code' ),
			esc_html__( 'Info', 'light-custom-code' ),
			'manage_options',
			self::SUBMENU_SLUG_INFO,
			array( $this, 'render_info_page' )
		);
	}

	// ---------------------------------------------------------------------------
	// Asset Enqueue
	// ---------------------------------------------------------------------------

	/**
	 * Enqueue CSS and JS only on the plugin's own admin pages.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed_hooks = array(
			'toplevel_page_' . self::MENU_SLUG,
			'custom-code_page_' . self::SUBMENU_SLUG_HF,
			'custom-code_page_' . self::SUBMENU_SLUG_CSS,
			'custom-code_page_' . self::SUBMENU_SLUG_INFO,
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		$php_settings  = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		$css_settings  = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		$html_settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		wp_enqueue_style(
			'lcc-admin',
			LCC_PLUGIN_URL . 'admin/css/admin.css',
			array( 'wp-codemirror' ),
			LCC_VERSION
		);

		wp_enqueue_script(
			'lcc-admin',
			LCC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-codemirror', 'csslint', 'jshint' ),
			LCC_VERSION,
			true
		);

		wp_localize_script(
			'lcc-admin',
			'lccData',
			array(
				'phpSettings'  => false !== $php_settings ? $php_settings : array(),
				'cssSettings'  => false !== $css_settings ? $css_settings : array(),
				'htmlSettings' => false !== $html_settings ? $html_settings : array(),
				'i18n'         => array(
					'confirmDelete'  => esc_html__( 'Are you sure you want to delete this snippet? This action cannot be undone.', 'light-custom-code' ),
					'unsavedChanges' => esc_html__( 'You have unsaved changes. Are you sure you want to leave?', 'light-custom-code' ),
				),
			)
		);
	}

	// ---------------------------------------------------------------------------
	// Form Processing
	// ---------------------------------------------------------------------------

	/**
	 * Process all POST form submissions.
	 *
	 * @since 1.0.0
	 */
	public function process_forms() {
		if ( ! isset( $_POST['lcc_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'light-custom-code' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['lcc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified inside each handler via check_admin_referer().

		switch ( $action ) {
			case 'save_snippet':
				$this->handle_save_snippet();
				break;
			case 'save_head_footer':
				$this->handle_save_head_footer();
				break;
			case 'save_css':
				$this->handle_save_css();
				break;
		}
	}

	/**
	 * Handle adding or editing a PHP snippet.
	 *
	 * @since 1.0.0
	 */
	private function handle_save_snippet() {
		check_admin_referer( self::NONCE_SNIPPET_FORM, 'lcc_nonce' );

		$snippets = get_option( self::OPTION_SNIPPETS, array() );
		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		$edit_id  = isset( $_POST['lcc_snippet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['lcc_snippet_id'] ) ) : '';
		$name     = isset( $_POST['lcc_snippet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lcc_snippet_name'] ) ) : '';
		$priority = isset( $_POST['lcc_snippet_priority'] ) ? absint( wp_unslash( $_POST['lcc_snippet_priority'] ) ) : 10;
		$active   = isset( $_POST['lcc_snippet_active'] ) && '1' === sanitize_key( wp_unslash( $_POST['lcc_snippet_active'] ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw PHP code stored as-is; sanitizing would corrupt it. Requires manage_options capability.
		$code     = isset( $_POST['lcc_snippet_code'] ) ? wp_unslash( $_POST['lcc_snippet_code'] ) : '';

		// ── Validation ────────────────────────────────────────────────────────
		$error_message = '';

		if ( '' === trim( $name ) ) {
			$error_message = __( 'Snippet name cannot be empty.', 'light-custom-code' );
		}

		// Validate PHP syntax only when saving as active; drafts (inactive) may
		// intentionally contain incomplete or broken code.
		if ( '' === $error_message && $active && '' !== trim( $code ) ) {
			$syntax_check = LCC_Validator::check( $code );
			if ( is_wp_error( $syntax_check ) ) {
				$error_message = $syntax_check->get_error_message();
			}
		}

		if ( '' !== $error_message ) {
			// Save what the user typed so the form can re-populate after redirect.
			$this->save_form_draft( $edit_id, $name, $code, $priority, $active );

			// Redirect back to the form, not to the list.
			$this->redirect_to_form_with_error( $edit_id, $error_message );
			return;
		}

		// ── Persist ───────────────────────────────────────────────────────────
		$priority = max( 1, min( 999, $priority ) );

		if ( '' !== $edit_id ) {
			$found = false;
			foreach ( $snippets as &$snippet ) {
				if ( isset( $snippet['id'] ) && $snippet['id'] === $edit_id ) {
					$snippet['name']     = $name;
					$snippet['code']     = $code;
					$snippet['priority'] = $priority;
					$snippet['active']   = $active;
					$snippet['modified'] = time();
					$found               = true;
					break;
				}
			}
			unset( $snippet );

			if ( ! $found ) {
				$this->redirect_to_form_with_error( $edit_id, __( 'Snippet not found.', 'light-custom-code' ) );
				return;
			}

			$message = __( 'Snippet updated successfully.', 'light-custom-code' );
		} else {
			if ( count( $snippets ) >= self::MAX_SNIPPETS ) {
				$this->redirect_to_form_with_error(
					'',
					sprintf(
						/* translators: %d: maximum number of snippets */
						__( 'You have reached the maximum number of snippets (%d).', 'light-custom-code' ),
						self::MAX_SNIPPETS
					)
				);
				return;
			}

			$snippets[] = array(
				'id'       => uniqid( 'lcc_', true ),
				'name'     => $name,
				'code'     => $code,
				'priority' => $priority,
				'active'   => $active,
				'created'  => time(),
				'modified' => time(),
			);

			$message = __( 'Snippet added successfully.', 'light-custom-code' );
		}

		// Clear any leftover draft on success.
		$this->clear_form_draft();

		update_option( self::OPTION_SNIPPETS, $snippets, false );
		LCC_Cache::rebuild();
		$this->redirect_with_notice( self::MENU_SLUG, 'success', $message );
	}

	/**
	 * Handle saving head and footer code.
	 *
	 * @since 1.0.0
	 */
	private function handle_save_head_footer() {
		check_admin_referer( self::NONCE_HF_FORM, 'lcc_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw HTML/script stored as-is. Requires manage_options.
		$head_code   = isset( $_POST['lcc_head_code'] ) ? wp_unslash( $_POST['lcc_head_code'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw HTML/script stored as-is. Requires manage_options.
		$footer_code = isset( $_POST['lcc_footer_code'] ) ? wp_unslash( $_POST['lcc_footer_code'] ) : '';

		update_option( self::OPTION_HEAD, $head_code, false );
		update_option( self::OPTION_FOOTER, $footer_code, false );

		$this->redirect_with_notice( self::SUBMENU_SLUG_HF, 'success', __( 'Head & Footer code saved successfully.', 'light-custom-code' ) );
	}

	/**
	 * Handle saving custom CSS.
	 *
	 * @since 1.0.0
	 */
	private function handle_save_css() {
		check_admin_referer( self::NONCE_CSS_FORM, 'lcc_nonce' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw CSS stored as-is. Requires manage_options.
		$global    = isset( $_POST['lcc_css_global'] ) ? wp_unslash( $_POST['lcc_css_global'] ) : '';
		$desktop   = isset( $_POST['lcc_css_desktop'] ) ? wp_unslash( $_POST['lcc_css_desktop'] ) : '';
		$tablet    = isset( $_POST['lcc_css_tablet'] ) ? wp_unslash( $_POST['lcc_css_tablet'] ) : '';
		$mobile    = isset( $_POST['lcc_css_mobile'] ) ? wp_unslash( $_POST['lcc_css_mobile'] ) : '';
		// phpcs:enable

		$tablet_bp = isset( $_POST['lcc_tablet_breakpoint'] ) ? absint( wp_unslash( $_POST['lcc_tablet_breakpoint'] ) ) : 1024;
		$mobile_bp = isset( $_POST['lcc_mobile_breakpoint'] ) ? absint( wp_unslash( $_POST['lcc_mobile_breakpoint'] ) ) : 768;

		$tablet_bp = max( 320, min( 3840, $tablet_bp ) );
		$mobile_bp = max( 320, min( 3840, $mobile_bp ) );

		update_option(
			self::OPTION_CSS,
			array(
				'global'            => $global,
				'desktop'           => $desktop,
				'tablet'            => $tablet,
				'mobile'            => $mobile,
				'tablet_breakpoint' => $tablet_bp,
				'mobile_breakpoint' => $mobile_bp,
			),
			false
		);

		$this->redirect_with_notice( self::SUBMENU_SLUG_CSS, 'success', __( 'Custom CSS saved successfully.', 'light-custom-code' ) );
	}

	// ---------------------------------------------------------------------------
	// Inline Actions — toggle / delete via GET
	// ---------------------------------------------------------------------------

	/**
	 * Process inline snippet actions (toggle or delete) triggered via GET links.
	 * Runs on admin_init so wp_safe_redirect fires before any HTML output.
	 *
	 * @since 1.0.0
	 */
	public function process_snippet_action() {
		if ( ! isset( $_GET['lcc_snippet_action'], $_GET['lcc_snippet_id'], $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action     = sanitize_key( wp_unslash( $_GET['lcc_snippet_action'] ) );
		$snippet_id = sanitize_text_field( wp_unslash( $_GET['lcc_snippet_id'] ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce value passed directly to wp_verify_nonce() which handles validation.
		if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), self::NONCE_SNIPPET_ACT . '_' . $snippet_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'light-custom-code' ) );
		}

		$snippets = get_option( self::OPTION_SNIPPETS, array() );
		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		if ( 'delete' === $action ) {
			$snippets = array_values(
				array_filter(
					$snippets,
					static function ( $s ) use ( $snippet_id ) {
						return isset( $s['id'] ) && $s['id'] !== $snippet_id;
					}
				)
			);
			update_option( self::OPTION_SNIPPETS, $snippets, false );
			LCC_Cache::rebuild();
			$this->redirect_with_notice( self::MENU_SLUG, 'success', __( 'Snippet deleted.', 'light-custom-code' ) );
			return;
		}

		if ( 'toggle' === $action ) {
			foreach ( $snippets as &$snippet ) {
				if ( isset( $snippet['id'] ) && $snippet['id'] === $snippet_id ) {
					$snippet['active']   = empty( $snippet['active'] );
					$snippet['modified'] = time();
					break;
				}
			}
			unset( $snippet );
			update_option( self::OPTION_SNIPPETS, $snippets, false );
			LCC_Cache::rebuild();
			$this->redirect_with_notice( self::MENU_SLUG, 'success', __( 'Snippet status updated.', 'light-custom-code' ) );
			return;
		}
	}

	// ---------------------------------------------------------------------------
	// Page Renderers
	// ---------------------------------------------------------------------------

	/**
	 * Render the PHP Snippets admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_snippets_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'light-custom-code' ) );
		}

		$snippets = get_option( self::OPTION_SNIPPETS, array() );
		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		$edit_id      = '';
		$edit_snippet = null;

		if ( isset( $_GET['lcc_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; identifies snippet to display in edit form.
			$edit_id = sanitize_text_field( wp_unslash( $_GET['lcc_edit'] ) );
			foreach ( $snippets as $s ) {
				if ( isset( $s['id'] ) && $s['id'] === $edit_id ) {
					$edit_snippet = $s;
					break;
				}
			}
		}

		$show_form = isset( $_GET['lcc_add'] ) || null !== $edit_snippet; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag; determines whether to show add form.
		?>
		<div class="wrap lcc-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'PHP Snippets', 'light-custom-code' ); ?></h1>
			<?php $this->display_notices(); ?>

			<div class="lcc-topbar">
				<div class="lcc-topbar__left">
					<?php $this->render_page_nav( 'snippets' ); ?>
				</div>
				<?php if ( ! $show_form ) : ?>
				<div class="lcc-topbar__right">
					<a href="<?php echo esc_url( add_query_arg( 'lcc_add', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) ); ?>" class="lcc-btn lcc-btn--primary">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Add Snippet', 'light-custom-code' ); ?>
					</a>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( $show_form ) : ?>
				<?php $this->render_snippet_form( $edit_snippet ); ?>
			<?php else : ?>
				<?php $this->render_snippets_table( $snippets ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the snippet add/edit form.
	 *
	 * @since  1.0.0
	 * @param  array|null $snippet Existing snippet data when editing, null for new.
	 */
	private function render_snippet_form( $snippet ) {
		$is_edit  = null !== $snippet;
		$name     = $is_edit ? $snippet['name'] : '';
		$code     = $is_edit ? $snippet['code'] : '';
		$priority = $is_edit ? absint( $snippet['priority'] ) : 10;
		$active   = $is_edit ? ! empty( $snippet['active'] ) : true;
		$edit_id  = $is_edit ? $snippet['id'] : '';

		// If a validation error was triggered on the previous submit, restore the
		// user's typed values from the draft transient so nothing is lost.
		$draft = $this->get_and_clear_form_draft();
		if ( null !== $draft ) {
			$name     = isset( $draft['name'] ) ? $draft['name'] : $name;
			$code     = isset( $draft['code'] ) ? $draft['code'] : $code;
			$priority = isset( $draft['priority'] ) ? absint( $draft['priority'] ) : $priority;
			$active   = isset( $draft['active'] ) ? (bool) $draft['active'] : $active;
		}

		// Retrieve a form-specific validation error (stored in its own transient,
		// not the generic notice system, so it never ends up in WP's notification
		// centre or gets swallowed by third-party plugins).
		$form_error = '';
		$form_error_transient = get_transient( 'lcc_form_error_' . get_current_user_id() );
		if ( false !== $form_error_transient && is_string( $form_error_transient ) ) {
			$form_error = $form_error_transient;
			delete_transient( 'lcc_form_error_' . get_current_user_id() );
		}
		?>
		<?php if ( '' !== $form_error ) : ?>
		<div class="lcc-form-error" role="alert">
			<span class="lcc-form-error__icon" aria-hidden="true">⚠</span>
			<div class="lcc-form-error__body">
				<strong><?php esc_html_e( 'Could not save snippet', 'light-custom-code' ); ?></strong>
				<p><?php echo esc_html( $form_error ); ?></p>
			</div>
		</div>
		<?php endif; ?>
		<form
			method="post"
			action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>"
			id="lcc-snippet-form"
			class="lcc-snippet-form"
		>
			<?php wp_nonce_field( self::NONCE_SNIPPET_FORM, 'lcc_nonce' ); ?>
			<input type="hidden" name="lcc_action" value="save_snippet">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="lcc_snippet_id" value="<?php echo esc_attr( $edit_id ); ?>">
			<?php endif; ?>

			<!-- Meta row: name, priority, active toggle -->
			<div class="lcc-meta-row">
				<div class="lcc-meta-row__name">
					<label for="lcc_snippet_name" class="lcc-label">
						<?php esc_html_e( 'Snippet Name', 'light-custom-code' ); ?>
						<span class="lcc-required" aria-hidden="true"> *</span>
					</label>
					<input
						type="text"
						id="lcc_snippet_name"
						name="lcc_snippet_name"
						value="<?php echo esc_attr( $name ); ?>"
						class="lcc-input"
						required
						placeholder="<?php esc_attr_e( 'e.g. Register custom post type', 'light-custom-code' ); ?>"
					>
				</div>

				<div class="lcc-meta-row__priority">
					<label for="lcc_snippet_priority" class="lcc-label"><?php esc_html_e( 'Priority', 'light-custom-code' ); ?></label>
					<input
						type="number"
						id="lcc_snippet_priority"
						name="lcc_snippet_priority"
						value="<?php echo esc_attr( $priority ); ?>"
						class="lcc-input lcc-input--short"
						min="1"
						max="999"
					>
					<p class="lcc-field-hint"><?php esc_html_e( 'Lower = runs first', 'light-custom-code' ); ?></p>
				</div>

				<div class="lcc-meta-row__active">
					<span class="lcc-label"><?php esc_html_e( 'Active', 'light-custom-code' ); ?></span>
					<label class="lcc-switch">
						<input type="hidden" name="lcc_snippet_active" value="0">
						<input
							type="checkbox"
							name="lcc_snippet_active"
							id="lcc_snippet_active"
							value="1"
							<?php checked( $active ); ?>
						>
						<span class="lcc-switch__track" aria-hidden="true">
							<span class="lcc-switch__thumb"></span>
						</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Activate this snippet', 'light-custom-code' ); ?></span>
					</label>
				</div>
			</div>

			<!-- Code editor -->
			<div class="lcc-editor-block">
				<div class="lcc-editor-block__bar">
					<div class="lcc-editor-block__dots" aria-hidden="true">
						<span></span><span></span><span></span>
					</div>
					<span class="lcc-editor-block__lang">PHP</span>
					<span class="lcc-editor-block__hint"><?php esc_html_e( 'No opening &lt;?php tag needed', 'light-custom-code' ); ?></span>
				</div>
				<textarea
					id="lcc_snippet_code"
					name="lcc_snippet_code"
					class="lcc-code-editor lcc-code-editor--php"
					rows="24"
					spellcheck="false"
				><?php echo esc_textarea( $code ); ?></textarea>
			</div>

			<!-- Form actions -->
			<div class="lcc-form-actions">
				<?php
				submit_button(
					$is_edit ? __( 'Update Snippet', 'light-custom-code' ) : __( 'Save Snippet', 'light-custom-code' ),
					'primary lcc-btn lcc-btn--primary',
					'submit',
					false
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" class="lcc-btn lcc-btn--ghost lcc-cancel-btn">
					<?php esc_html_e( 'Cancel', 'light-custom-code' ); ?>
				</a>
			</div>
		</form>
		<?php
	}

	/**
	 * Render the snippets list table.
	 *
	 * @since  1.0.0
	 * @param  array $snippets All stored snippets.
	 */
	private function render_snippets_table( array $snippets ) {
		if ( empty( $snippets ) ) {
			?>
			<div class="lcc-empty-state">
				<span class="dashicons dashicons-editor-code lcc-empty-state__icon" aria-hidden="true"></span>
				<h2 class="lcc-empty-state__title"><?php esc_html_e( 'No snippets yet', 'light-custom-code' ); ?></h2>
				<p class="lcc-empty-state__text"><?php esc_html_e( 'Add your first PHP snippet to extend WordPress without a child theme.', 'light-custom-code' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( 'lcc_add', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) ); ?>" class="lcc-btn lcc-btn--primary">
					<?php esc_html_e( 'Add Your First Snippet', 'light-custom-code' ); ?>
				</a>
			</div>
			<?php
			return;
		}

		usort(
			$snippets,
			static function ( $a, $b ) {
				return absint( $a['priority'] ) - absint( $b['priority'] );
			}
		);
		?>
		<div class="lcc-card">
			<table class="wp-list-table widefat fixed striped lcc-table">
				<thead>
					<tr>
						<th scope="col" class="lcc-col-status"><?php esc_html_e( 'Active', 'light-custom-code' ); ?></th>
						<th scope="col" class="lcc-col-name column-primary"><?php esc_html_e( 'Snippet Name', 'light-custom-code' ); ?></th>
						<th scope="col" class="lcc-col-priority"><?php esc_html_e( 'Priority', 'light-custom-code' ); ?></th>
						<th scope="col" class="lcc-col-modified"><?php esc_html_e( 'Last Modified', 'light-custom-code' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $snippets as $snippet ) : ?>
						<?php
						$snippet_id   = isset( $snippet['id'] ) ? $snippet['id'] : '';
						$snippet_name = isset( $snippet['name'] ) ? $snippet['name'] : __( '(Unnamed)', 'light-custom-code' );
						$is_active    = ! empty( $snippet['active'] );
						$priority     = isset( $snippet['priority'] ) ? absint( $snippet['priority'] ) : 10;
						$modified     = isset( $snippet['modified'] ) ? absint( $snippet['modified'] ) : 0;

						$nonce      = wp_create_nonce( self::NONCE_SNIPPET_ACT . '_' . $snippet_id );
						$toggle_url = add_query_arg(
							array(
								'page'               => self::MENU_SLUG,
								'lcc_snippet_action' => 'toggle',
								'lcc_snippet_id'     => $snippet_id,
								'_wpnonce'           => $nonce,
							),
							admin_url( 'admin.php' )
						);
						$edit_url   = add_query_arg(
							array(
								'page'     => self::MENU_SLUG,
								'lcc_edit' => $snippet_id,
							),
							admin_url( 'admin.php' )
						);
						$delete_url = add_query_arg(
							array(
								'page'               => self::MENU_SLUG,
								'lcc_snippet_action' => 'delete',
								'lcc_snippet_id'     => $snippet_id,
								'_wpnonce'           => $nonce,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td class="lcc-col-status">
								<a
									href="<?php echo esc_url( $toggle_url ); ?>"
									class="lcc-switch-link<?php echo $is_active ? ' lcc-switch-link--on' : ''; ?>"
									aria-label="<?php
										if ( $is_active ) {
											/* translators: %s: snippet name */
											echo esc_attr( sprintf( __( 'Deactivate "%s"', 'light-custom-code' ), $snippet_name ) );
										} else {
											/* translators: %s: snippet name */
											echo esc_attr( sprintf( __( 'Activate "%s"', 'light-custom-code' ), $snippet_name ) );
										}
									?>"
								>
									<span class="lcc-switch-link__track" aria-hidden="true">
										<span class="lcc-switch-link__thumb"></span>
									</span>
								</a>
							</td>

							<td class="lcc-col-name column-primary">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="lcc-table__name">
									<?php echo esc_html( $snippet_name ); ?>
								</a>
								<div class="row-actions">
									<span class="edit">
										<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'light-custom-code' ); ?></a>
										&nbsp;|&nbsp;
									</span>
									<span class="delete">
										<a href="<?php echo esc_url( $delete_url ); ?>" class="submitdelete lcc-delete-snippet">
											<?php esc_html_e( 'Delete', 'light-custom-code' ); ?>
										</a>
									</span>
								</div>
								<button type="button" class="toggle-row">
									<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'light-custom-code' ); ?></span>
								</button>
							</td>

							<td class="lcc-col-priority"><?php echo esc_html( $priority ); ?></td>

							<td class="lcc-col-modified">
								<?php
								echo $modified > 0
									? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $modified ) )
									: '—';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="lcc-table-count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: current count, 2: maximum count */
					__( '%1$d of %2$d snippets', 'light-custom-code' ),
					count( $snippets ),
					self::MAX_SNIPPETS
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the Head & Footer admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_head_footer_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'light-custom-code' ) );
		}

		$head_code   = get_option( self::OPTION_HEAD, '' );
		$footer_code = get_option( self::OPTION_FOOTER, '' );

		?>
		<div class="wrap lcc-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Head & Footer Code', 'light-custom-code' ); ?></h1>
			<?php $this->display_notices(); ?>

			<div class="lcc-topbar">
				<div class="lcc-topbar__left">
					<?php $this->render_page_nav( 'head_footer' ); ?>
				</div>
				<div class="lcc-topbar__right">
					<button type="button" class="lcc-editor-theme-toggle lcc-btn lcc-btn--ghost" id="lcc-editor-theme-toggle" aria-label="<?php esc_attr_e( 'Switch editor theme', 'light-custom-code' ); ?>">
						<span class="lcc-editor-theme-toggle__icon" aria-hidden="true"></span>
						<span class="lcc-editor-theme-toggle__label"></span>
					</button>
				</div>
			</div>

			<p class="lcc-page-desc"><?php esc_html_e( 'Raw HTML output on every public page. Use for meta tags, Google Tag Manager, verification codes, and similar scripts.', 'light-custom-code' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG_HF ) ); ?>" id="lcc-hf-form">
				<?php wp_nonce_field( self::NONCE_HF_FORM, 'lcc_nonce' ); ?>
				<input type="hidden" name="lcc_action" value="save_head_footer">

				<div class="lcc-two-col">
					<div class="lcc-editor-block">
						<div class="lcc-editor-block__bar">
							<div class="lcc-editor-block__dots" aria-hidden="true">
								<span></span><span></span><span></span>
							</div>
							<span class="lcc-editor-block__lang">HTML</span>
							<span class="lcc-editor-block__label"><?php esc_html_e( 'Inside &lt;head&gt;', 'light-custom-code' ); ?></span>
						</div>
						<textarea
							id="lcc_head_code"
							name="lcc_head_code"
							class="lcc-code-editor lcc-code-editor--html"
							rows="22"
							spellcheck="false"
						><?php echo esc_textarea( $head_code ); ?></textarea>
					</div>

					<div class="lcc-editor-block">
						<div class="lcc-editor-block__bar">
							<div class="lcc-editor-block__dots" aria-hidden="true">
								<span></span><span></span><span></span>
							</div>
							<span class="lcc-editor-block__lang">HTML</span>
							<span class="lcc-editor-block__label"><?php esc_html_e( 'Before &lt;/body&gt;', 'light-custom-code' ); ?></span>
						</div>
						<textarea
							id="lcc_footer_code"
							name="lcc_footer_code"
							class="lcc-code-editor lcc-code-editor--html"
							rows="22"
							spellcheck="false"
						><?php echo esc_textarea( $footer_code ); ?></textarea>
					</div>
				</div>

				<div class="lcc-form-actions">
					<?php submit_button( __( 'Save Changes', 'light-custom-code' ), 'primary lcc-btn lcc-btn--primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Custom CSS admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_css_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'light-custom-code' ) );
		}

		$css_options = get_option( self::OPTION_CSS, array() );
		if ( ! is_array( $css_options ) ) {
			$css_options = array();
		}

		$global    = isset( $css_options['global'] ) ? $css_options['global'] : '';
		$desktop   = isset( $css_options['desktop'] ) ? $css_options['desktop'] : '';
		$tablet    = isset( $css_options['tablet'] ) ? $css_options['tablet'] : '';
		$mobile    = isset( $css_options['mobile'] ) ? $css_options['mobile'] : '';
		$tablet_bp = isset( $css_options['tablet_breakpoint'] ) ? absint( $css_options['tablet_breakpoint'] ) : 1024;
		$mobile_bp = isset( $css_options['mobile_breakpoint'] ) ? absint( $css_options['mobile_breakpoint'] ) : 768;

		?>
		<div class="wrap lcc-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Custom CSS', 'light-custom-code' ); ?></h1>
			<?php $this->display_notices(); ?>

			<div class="lcc-topbar">
				<div class="lcc-topbar__left">
					<?php $this->render_page_nav( 'css' ); ?>
				</div>
				<div class="lcc-topbar__right">
					<button type="button" class="lcc-editor-theme-toggle lcc-btn lcc-btn--ghost" id="lcc-editor-theme-toggle" aria-label="<?php esc_attr_e( 'Switch editor theme', 'light-custom-code' ); ?>">
						<span class="lcc-editor-theme-toggle__icon" aria-hidden="true"></span>
						<span class="lcc-editor-theme-toggle__label"></span>
					</button>
				</div>
			</div>

			<p class="lcc-page-desc"><?php esc_html_e( 'CSS added after your theme styles. Responsive tabs are automatically wrapped in the correct media queries.', 'light-custom-code' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG_CSS ) ); ?>" id="lcc-css-form">
				<?php wp_nonce_field( self::NONCE_CSS_FORM, 'lcc_nonce' ); ?>
				<input type="hidden" name="lcc_action" value="save_css">

				<!-- Breakpoints row -->
				<div class="lcc-breakpoints">
					<span class="lcc-breakpoints__label"><?php esc_html_e( 'Breakpoints:', 'light-custom-code' ); ?></span>

					<label class="lcc-breakpoints__field">
						<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
						<span class="lcc-breakpoints__name"><?php esc_html_e( 'Tablet max-width', 'light-custom-code' ); ?></span>
						<input
							type="number"
							name="lcc_tablet_breakpoint"
							id="lcc_tablet_breakpoint"
							value="<?php echo esc_attr( $tablet_bp ); ?>"
							class="lcc-input lcc-input--short"
							min="320"
							max="3840"
						>
						<span class="lcc-breakpoints__unit">px</span>
					</label>

					<label class="lcc-breakpoints__field">
						<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
						<span class="lcc-breakpoints__name"><?php esc_html_e( 'Mobile max-width', 'light-custom-code' ); ?></span>
						<input
							type="number"
							name="lcc_mobile_breakpoint"
							id="lcc_mobile_breakpoint"
							value="<?php echo esc_attr( $mobile_bp ); ?>"
							class="lcc-input lcc-input--short"
							min="320"
							max="3840"
						>
						<span class="lcc-breakpoints__unit">px</span>
					</label>
				</div>

				<!-- CSS editor tabs -->
				<div class="lcc-css-tabs">
					<nav class="lcc-css-tabs__nav" role="tablist" aria-label="<?php esc_attr_e( 'CSS editor tabs', 'light-custom-code' ); ?>">
						<button type="button" class="lcc-css-tabs__btn lcc-css-tabs__btn--active" role="tab" aria-selected="true" aria-controls="lcc-tab-global" id="lcc-tabhead-global">
							<?php esc_html_e( 'Global', 'light-custom-code' ); ?>
						</button>
						<button type="button" class="lcc-css-tabs__btn" role="tab" aria-selected="false" aria-controls="lcc-tab-desktop" id="lcc-tabhead-desktop">
							<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
							<?php esc_html_e( 'Desktop', 'light-custom-code' ); ?>
						</button>
						<button type="button" class="lcc-css-tabs__btn" role="tab" aria-selected="false" aria-controls="lcc-tab-tablet" id="lcc-tabhead-tablet">
							<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
							<?php esc_html_e( 'Tablet', 'light-custom-code' ); ?>
						</button>
						<button type="button" class="lcc-css-tabs__btn" role="tab" aria-selected="false" aria-controls="lcc-tab-mobile" id="lcc-tabhead-mobile">
							<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
							<?php esc_html_e( 'Mobile', 'light-custom-code' ); ?>
						</button>
					</nav>

					<div id="lcc-tab-global" class="lcc-css-tabs__panel" role="tabpanel" aria-labelledby="lcc-tabhead-global">
						<div class="lcc-editor-block">
							<div class="lcc-editor-block__bar">
								<div class="lcc-editor-block__dots" aria-hidden="true"><span></span><span></span><span></span></div>
								<span class="lcc-editor-block__lang">CSS</span>
								<span class="lcc-editor-block__hint"><?php esc_html_e( 'All screen sizes — no media query', 'light-custom-code' ); ?></span>
							</div>
							<textarea id="lcc_css_global" name="lcc_css_global" class="lcc-code-editor lcc-code-editor--css" rows="22" spellcheck="false"><?php echo esc_textarea( $global ); ?></textarea>
						</div>
					</div>

					<div id="lcc-tab-desktop" class="lcc-css-tabs__panel lcc-css-tabs__panel--hidden" role="tabpanel" aria-labelledby="lcc-tabhead-desktop">
						<div class="lcc-editor-block">
							<div class="lcc-editor-block__bar">
								<div class="lcc-editor-block__dots" aria-hidden="true"><span></span><span></span><span></span></div>
								<span class="lcc-editor-block__lang">CSS</span>
								<span class="lcc-editor-block__hint">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: min-width in pixels */
											__( '@media (min-width: %dpx)', 'light-custom-code' ),
											$tablet_bp + 1
										)
									);
									?>
								</span>
							</div>
							<textarea id="lcc_css_desktop" name="lcc_css_desktop" class="lcc-code-editor lcc-code-editor--css" rows="22" spellcheck="false"><?php echo esc_textarea( $desktop ); ?></textarea>
						</div>
					</div>

					<div id="lcc-tab-tablet" class="lcc-css-tabs__panel lcc-css-tabs__panel--hidden" role="tabpanel" aria-labelledby="lcc-tabhead-tablet">
						<div class="lcc-editor-block">
							<div class="lcc-editor-block__bar">
								<div class="lcc-editor-block__dots" aria-hidden="true"><span></span><span></span><span></span></div>
								<span class="lcc-editor-block__lang">CSS</span>
								<span class="lcc-editor-block__hint">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: max-width in pixels */
											__( '@media (max-width: %dpx)', 'light-custom-code' ),
											$tablet_bp
										)
									);
									?>
								</span>
							</div>
							<textarea id="lcc_css_tablet" name="lcc_css_tablet" class="lcc-code-editor lcc-code-editor--css" rows="22" spellcheck="false"><?php echo esc_textarea( $tablet ); ?></textarea>
						</div>
					</div>

					<div id="lcc-tab-mobile" class="lcc-css-tabs__panel lcc-css-tabs__panel--hidden" role="tabpanel" aria-labelledby="lcc-tabhead-mobile">
						<div class="lcc-editor-block">
							<div class="lcc-editor-block__bar">
								<div class="lcc-editor-block__dots" aria-hidden="true"><span></span><span></span><span></span></div>
								<span class="lcc-editor-block__lang">CSS</span>
								<span class="lcc-editor-block__hint">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: max-width in pixels */
											__( '@media (max-width: %dpx)', 'light-custom-code' ),
											$mobile_bp
										)
									);
									?>
								</span>
							</div>
							<textarea id="lcc_css_mobile" name="lcc_css_mobile" class="lcc-code-editor lcc-code-editor--css" rows="22" spellcheck="false"><?php echo esc_textarea( $mobile ); ?></textarea>
						</div>
					</div>
				</div>

				<div class="lcc-form-actions">
					<?php submit_button( __( 'Save Changes', 'light-custom-code' ), 'primary lcc-btn lcc-btn--primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	// ---------------------------------------------------------------------------
	// Info Page
	// ---------------------------------------------------------------------------

	/**
	 * Render the Info admin page.
	 *
	 * Shows plugin version, system info, recovery URL, and quick-start guide.
	 *
	 * @since 1.0.0
	 */
	public function render_info_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'light-custom-code' ) );
		}

		global $wp_version;

		$snippet_count = 0;
		$active_count  = 0;
		$snippets      = get_option( self::OPTION_SNIPPETS, array() );
		if ( is_array( $snippets ) ) {
			$snippet_count = count( $snippets );
			foreach ( $snippets as $s ) {
				if ( ! empty( $s['active'] ) ) {
					++$active_count;
				}
			}
		}

		$has_head   = '' !== trim( get_option( self::OPTION_HEAD, '' ) );
		$has_footer = '' !== trim( get_option( self::OPTION_FOOTER, '' ) );
		$css_opts   = get_option( self::OPTION_CSS, array() );
		$has_css    = is_array( $css_opts ) && (
			'' !== trim( isset( $css_opts['global'] ) ? $css_opts['global'] : '' ) ||
			'' !== trim( isset( $css_opts['desktop'] ) ? $css_opts['desktop'] : '' ) ||
			'' !== trim( isset( $css_opts['tablet'] ) ? $css_opts['tablet'] : '' ) ||
			'' !== trim( isset( $css_opts['mobile'] ) ? $css_opts['mobile'] : '' )
		);

		$recovery_url = LCC_Recovery::get_url();
		?>
		<div class="wrap lcc-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Info', 'light-custom-code' ); ?></h1>
			<?php $this->display_notices(); ?>

			<div class="lcc-topbar">
				<div class="lcc-topbar__left">
					<?php $this->render_page_nav( 'info' ); ?>
				</div>
			</div>

			<div class="lcc-info-grid">

				<!-- Plugin & System Info -->
				<div class="lcc-info-card">
					<h2 class="lcc-info-card__title">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						<?php esc_html_e( 'Plugin Information', 'light-custom-code' ); ?>
					</h2>
					<table class="lcc-info-table">
						<tr>
							<th><?php esc_html_e( 'Plugin Version', 'light-custom-code' ); ?></th>
							<td><code><?php echo esc_html( LCC_VERSION ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WordPress Version', 'light-custom-code' ); ?></th>
							<td><code><?php echo esc_html( $wp_version ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'light-custom-code' ); ?></th>
							<td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Active Snippets', 'light-custom-code' ); ?></th>
							<td>
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: active count, 2: total count */
										__( '%1$d of %2$d active', 'light-custom-code' ),
										$active_count,
										$snippet_count
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Head Code', 'light-custom-code' ); ?></th>
							<td>
								<span class="lcc-status-dot-sm <?php echo $has_head ? 'lcc-status-dot-sm--on' : 'lcc-status-dot-sm--off'; ?>"></span>
								<?php echo $has_head ? esc_html__( 'In use', 'light-custom-code' ) : esc_html__( 'Empty', 'light-custom-code' ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Footer Code', 'light-custom-code' ); ?></th>
							<td>
								<span class="lcc-status-dot-sm <?php echo $has_footer ? 'lcc-status-dot-sm--on' : 'lcc-status-dot-sm--off'; ?>"></span>
								<?php echo $has_footer ? esc_html__( 'In use', 'light-custom-code' ) : esc_html__( 'Empty', 'light-custom-code' ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Custom CSS', 'light-custom-code' ); ?></th>
							<td>
								<span class="lcc-status-dot-sm <?php echo $has_css ? 'lcc-status-dot-sm--on' : 'lcc-status-dot-sm--off'; ?>"></span>
								<?php echo $has_css ? esc_html__( 'In use', 'light-custom-code' ) : esc_html__( 'Empty', 'light-custom-code' ); ?>
							</td>
						</tr>
					</table>
				</div>

				<!-- Emergency Recovery -->
				<div class="lcc-info-card lcc-info-card--warning">
					<h2 class="lcc-info-card__title">
						<span class="dashicons dashicons-sos" aria-hidden="true"></span>
						<?php esc_html_e( 'Emergency Recovery', 'light-custom-code' ); ?>
					</h2>
					<p class="lcc-info-card__desc">
						<?php esc_html_e( 'If a snippet causes a fatal error and your WordPress admin becomes inaccessible, visit the URL below. It will instantly deactivate all snippets and restore access.', 'light-custom-code' ); ?>
					</p>
					<div class="lcc-recovery-box">
						<code class="lcc-recovery-box__url"><?php echo esc_url( $recovery_url ); ?></code>
						<button
							type="button"
							class="lcc-copy-btn"
							data-copy="<?php echo esc_attr( $recovery_url ); ?>"
							aria-label="<?php esc_attr_e( 'Copy recovery URL', 'light-custom-code' ); ?>"
						>
							<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
							<span class="lcc-copy-btn__label"><?php esc_html_e( 'Copy', 'light-custom-code' ); ?></span>
						</button>
					</div>
					<p class="lcc-info-card__note">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php esc_html_e( 'This URL is single-use and regenerates automatically after each use. Save it somewhere safe.', 'light-custom-code' ); ?>
					</p>
				</div>

				<!-- Quick Start -->
				<div class="lcc-info-card">
					<h2 class="lcc-info-card__title">
						<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'Quick Start', 'light-custom-code' ); ?>
					</h2>
					<ol class="lcc-quickstart">
						<li>
							<strong><?php esc_html_e( 'PHP Snippets', 'light-custom-code' ); ?></strong>
							<?php esc_html_e( 'Add PHP code that runs like functions.php — without a child theme. Enter code without an opening &lt;?php tag.', 'light-custom-code' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Head &amp; Footer', 'light-custom-code' ); ?></strong>
							<?php esc_html_e( 'Inject HTML into &lt;head&gt; (meta tags, analytics setup) or before &lt;/body&gt; (GTM noscript, chat widgets).', 'light-custom-code' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Custom CSS', 'light-custom-code' ); ?></strong>
							<?php esc_html_e( 'Write CSS for all screens, or use the Desktop / Tablet / Mobile tabs — each is automatically wrapped in the correct media query.', 'light-custom-code' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Safety tip', 'light-custom-code' ); ?></strong>
							<?php esc_html_e( 'Save the Emergency Recovery URL above before adding complex snippets. Active snippets are syntax-checked before saving.', 'light-custom-code' ); ?>
						</li>
					</ol>
				</div>

				<!-- About -->
				<div class="lcc-info-card lcc-info-card--about">
					<div class="lcc-about">
						<div class="lcc-about__logo">
							<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
						</div>
						<div class="lcc-about__body">
							<h3 class="lcc-about__name">Light Custom Code</h3>
							<p class="lcc-about__tagline"><?php esc_html_e( 'A Slim Plugins product — lightweight, secure, focused.', 'light-custom-code' ); ?></p>
							<div class="lcc-about__links">
								<a href="https://slimplugins.com" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'slimplugins.com', 'light-custom-code' ); ?>
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
								</a>
								<a href="https://wordpress.org/support/plugin/light-custom-code/" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Support', 'light-custom-code' ); ?>
									<span class="dashicons dashicons-external" aria-hidden="true"></span>
								</a>
							</div>
						</div>
					</div>
				</div>

			</div><!-- .lcc-info-grid -->
		</div><!-- .wrap -->
		<?php
	}

	// ---------------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------------

	/**
	 * Render the top navigation shared across all plugin pages.
	 *
	 * @since  1.0.0
	 * @param  string $active Active key: 'snippets', 'head_footer', or 'css'.
	 */
	private function render_page_nav( $active ) {
		$tabs = array(
			'snippets'    => array(
				'label' => __( 'PHP Snippets', 'light-custom-code' ),
				'url'   => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
			),
			'head_footer' => array(
				'label' => __( 'Head &amp; Footer', 'light-custom-code' ),
				'url'   => admin_url( 'admin.php?page=' . self::SUBMENU_SLUG_HF ),
			),
			'css'         => array(
				'label' => __( 'Custom CSS', 'light-custom-code' ),
				'url'   => admin_url( 'admin.php?page=' . self::SUBMENU_SLUG_CSS ),
			),
			'info'        => array(
				'label' => __( 'Info', 'light-custom-code' ),
				'url'   => admin_url( 'admin.php?page=' . self::SUBMENU_SLUG_INFO ),
			),
		);
		?>
		<nav class="lcc-nav" aria-label="<?php esc_attr_e( 'Plugin sections', 'light-custom-code' ); ?>">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a
					href="<?php echo esc_url( $tab['url'] ); ?>"
					class="lcc-nav__tab<?php echo $active === $key ? ' lcc-nav__tab--active' : ''; ?>"
					<?php echo $active === $key ? 'aria-current="page"' : ''; ?>
				><?php echo wp_kses( $tab['label'], array() ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Display any queued admin notice stored in a transient.
	 *
	 * @since 1.0.0
	 */
	private function display_notices() {
		$notice = get_transient( 'lcc_admin_notice_' . get_current_user_id() );
		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'lcc_admin_notice_' . get_current_user_id() );

		$type    = isset( $notice['type'] ) && 'error' === $notice['type'] ? 'error' : 'success';
		$message = isset( $notice['message'] ) ? $notice['message'] : '';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Save the submitted snippet form fields to a short-lived transient so
	 * the form can be re-populated after a validation-error redirect.
	 *
	 * @since  1.0.0
	 * @param  string $edit_id  Snippet ID when editing, empty string for new.
	 * @param  string $name     Snippet name.
	 * @param  string $code     PHP code.
	 * @param  int    $priority Execution priority.
	 * @param  bool   $active   Whether the snippet is active.
	 */
	private function save_form_draft( $edit_id, $name, $code, $priority, $active ) {
		set_transient(
			'lcc_form_draft_' . get_current_user_id(),
			array(
				'edit_id'  => $edit_id,
				'name'     => $name,
				'code'     => $code,
				'priority' => $priority,
				'active'   => $active,
			),
			120 // Two minutes — enough time for the redirect + page load.
		);
	}

	/**
	 * Retrieve and delete the saved form draft transient.
	 *
	 * @since  1.0.0
	 * @return array|null Draft data or null if none exists.
	 */
	private function get_and_clear_form_draft() {
		$key   = 'lcc_form_draft_' . get_current_user_id();
		$draft = get_transient( $key );
		if ( is_array( $draft ) ) {
			delete_transient( $key );
			return $draft;
		}
		return null;
	}

	/**
	 * Delete any saved form draft (called on successful save).
	 *
	 * @since 1.0.0
	 */
	private function clear_form_draft() {
		delete_transient( 'lcc_form_draft_' . get_current_user_id() );
	}

	/**
	 * Store an error notice and redirect back to the snippet form.
	 *
	 * For new snippets, redirects to the "add" form.
	 * For edits, redirects back to the same snippet's edit form.
	 *
	 * @since  1.0.0
	 * @param  string $edit_id Snippet ID when editing, empty string for new.
	 * @param  string $message Error message to display.
	 */
	private function redirect_to_form_with_error( $edit_id, $message ) {
		// Store the error message in a short-lived transient keyed to this user.
		// We use a dedicated transient (not the generic notice one) so WordPress
		// and third-party plugins cannot route it to the notification centre.
		set_transient(
			'lcc_form_error_' . get_current_user_id(),
			$message,
			120
		);

		if ( '' !== $edit_id ) {
			$url = add_query_arg(
				array(
					'page'     => self::MENU_SLUG,
					'lcc_edit' => rawurlencode( $edit_id ),
				),
				admin_url( 'admin.php' )
			);
		} else {
			$url = add_query_arg(
				array(
					'page'    => self::MENU_SLUG,
					'lcc_add' => '1',
				),
				admin_url( 'admin.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Store a notice in a transient and redirect to an admin page.
	 *
	 * @since  1.0.0
	 * @param  string $page    Admin menu slug.
	 * @param  string $type    'success' or 'error'.
	 * @param  string $message Human-readable message.
	 */
	private function redirect_with_notice( $page, $type, $message ) {
		set_transient(
			'lcc_admin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}
}
