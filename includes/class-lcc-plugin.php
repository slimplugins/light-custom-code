<?php
/**
 * Core plugin bootstrap class.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Plugin
 *
 * Singleton that wires up all sub-systems (admin, frontend, cache).
 *
 * @since 1.0.0
 */
class LCC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var LCC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  1.0.0
	 * @return LCC_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Require all dependency files.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// Already loaded in main file: activator, cache, recovery, validator.
		require_once LCC_PLUGIN_DIR . 'includes/class-lcc-frontend.php';

		if ( is_admin() ) {
			require_once LCC_PLUGIN_DIR . 'includes/class-lcc-admin.php';
		}
	}

	/**
	 * Instantiate sub-systems and register the textdomain.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		// Frontend always runs.
		new LCC_Frontend();

		// Admin only in the dashboard.
		if ( is_admin() ) {
			new LCC_Admin();
			add_action( 'admin_notices', array( $this, 'show_recovery_notices' ) );
		}

		// Settings-link on the plugins list screen.
		add_filter( 'plugin_action_links_' . LCC_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Display admin notices related to recovery events.
	 *
	 * Shows:
	 * - A success message after a recovery URL was used.
	 * - A warning if snippets were auto-disabled due to a fatal error.
	 *
	 * @since 1.0.0
	 */
	public function show_recovery_notices() {
		// Manual recovery via URL.
		if ( isset( $_GET['lcc_recovered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Light Custom Code — Recovery complete.', 'light-custom-code' ),
				esc_html__( 'All snippets have been deactivated. You can now safely edit or delete the problematic snippet.', 'light-custom-code' )
			);
		}

		// Auto-disable after fatal error.
		$last_error = LCC_Recovery::get_and_clear_last_error();
		if ( '' !== $last_error ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p><strong>%s</strong><br>%s<br><code>%s</code></p></div>',
				esc_html__( 'Light Custom Code — All snippets were automatically deactivated.', 'light-custom-code' ),
				esc_html__( 'A fatal PHP error was detected during snippet execution. The site has been restored. Please review and fix the faulty snippet before reactivating it.', 'light-custom-code' ),
				esc_html( $last_error )
			);
		}
	}

	/**
	 * Add a "Settings" quick link on the Plugins screen.
	 *
	 * @since  1.0.0
	 * @param  string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=light-custom-code' ) ),
			esc_html__( 'Settings', 'light-custom-code' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
