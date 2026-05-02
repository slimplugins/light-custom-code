<?php
/**
 * Plugin activation handler.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Activator
 *
 * Handles tasks that must run on plugin activation.
 *
 * @since 1.0.0
 */
class LCC_Activator {

	/**
	 * Run all activation routines.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::set_default_options();
		self::create_cache_directory();

		// Ensure a recovery key exists from the first activation.
		if ( false === get_option( 'lcc_recovery_key' ) ) {
			update_option( 'lcc_recovery_key', bin2hex( random_bytes( 16 ) ), false );
		}
	}

	/**
	 * Register default option values so they exist immediately after activation.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		if ( false === get_option( 'lcc_snippets' ) ) {
			update_option( 'lcc_snippets', array(), false );
		}

		if ( false === get_option( 'lcc_head_code' ) ) {
			update_option( 'lcc_head_code', '', false );
		}

		if ( false === get_option( 'lcc_footer_code' ) ) {
			update_option( 'lcc_footer_code', '', false );
		}

		if ( false === get_option( 'lcc_css' ) ) {
			update_option(
				'lcc_css',
				array(
					'global'            => '',
					'desktop'           => '',
					'tablet'            => '',
					'mobile'            => '',
					'tablet_breakpoint' => 1024,
					'mobile_breakpoint' => 768,
				),
				false
			);
		}
	}

	/**
	 * Create the cache directory inside the uploads folder and protect it
	 * with an .htaccess file and a silent index.php.
	 *
	 * Uses WP_Filesystem via LCC_Filesystem so no direct PHP file functions
	 * are needed.
	 *
	 * @since 1.0.0
	 */
	public static function create_cache_directory() {
		$cache_dir = self::get_cache_dir();

		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		// Prevent direct HTTP access via Apache / LiteSpeed.
		$htaccess_file = $cache_dir . '/.htaccess';
		if ( ! LCC_Filesystem::exists( $htaccess_file ) ) {
			// Deny HTTP access to all files in this directory:
			// active-snippets.php, .running, .disabled, index.php.
			LCC_Filesystem::write( $htaccess_file, "deny from all\n" );
		}

		// Prevent directory listing and return a blank response on direct access.
		$index_file = $cache_dir . '/index.php';
		if ( ! LCC_Filesystem::exists( $index_file ) ) {
			LCC_Filesystem::write( $index_file, '<?php // Silence is golden.' );
		}
	}

	/**
	 * Return the absolute path to the plugin cache directory.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_cache_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'light-custom-code';
	}
}
