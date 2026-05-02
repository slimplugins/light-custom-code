<?php
/**
 * Filesystem abstraction helper.
 *
 * Wraps WP_Filesystem so the rest of the plugin never calls file_put_contents
 * or other direct PHP file functions. Always uses the 'direct' transport
 * (WP_Filesystem_Direct) which is available in all standard WordPress
 * environments and does not require FTP/SSH credentials.
 *
 * Why 'direct' instead of asking WP to choose?
 * ─────────────────────────────────────────────
 * WP_Filesystem() can prompt for FTP credentials when the web server does not
 * own the files. Our cache and protection files are created inside the uploads
 * folder, which the web server always owns, so 'direct' is always correct and
 * we never need a credentials form.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Filesystem
 *
 * @since 1.0.0
 */
class LCC_Filesystem {

	/**
	 * Initialise and return a WP_Filesystem_Direct instance.
	 *
	 * Loads the WP_Filesystem base class and the Direct transport if they
	 * have not been loaded yet, then returns a ready-to-use instance.
	 *
	 * @since  1.0.0
	 * @return WP_Filesystem_Direct
	 */
	public static function get() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Bootstrap WP_Filesystem with the 'direct' transport explicitly so we
		// never get a credentials prompt regardless of server configuration.
		if ( ! ( $wp_filesystem instanceof WP_Filesystem_Base ) ) {
			WP_Filesystem( false, false, true ); // args: credentials, context, allow_relaxed_file_ownership
		}

		// If the global is still not a direct instance (e.g. another transport
		// was set externally) create our own Direct instance directly.
		if ( ! ( $wp_filesystem instanceof WP_Filesystem_Direct ) ) {
			if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			}
			return new WP_Filesystem_Direct( array() );
		}

		return $wp_filesystem;
	}

	/**
	 * Write $content to $file_path, creating the file if it does not exist.
	 *
	 * Equivalent to file_put_contents( $path, $content, LOCK_EX ) but uses
	 * WP_Filesystem so it satisfies WordPress coding standards.
	 *
	 * @since  1.0.0
	 * @param  string $file_path Absolute path to the destination file.
	 * @param  string $content   Content to write.
	 * @return bool              True on success, false on failure.
	 */
	public static function write( $file_path, $content ) {
		$fs = self::get();
		return $fs->put_contents( $file_path, $content, FS_CHMOD_FILE );
	}

	/**
	 * Check whether a path exists (file or directory).
	 *
	 * @since  1.0.0
	 * @param  string $path Absolute path to check.
	 * @return bool
	 */
	public static function exists( $path ) {
		$fs = self::get();
		return $fs->exists( $path );
	}

	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * @since  1.0.0
	 * @param  string $dir_path Absolute path to the directory.
	 * @return bool             True on success, false on failure.
	 */
	public static function delete_dir( $dir_path ) {
		$fs = self::get();
		return $fs->delete( $dir_path, true ); // true = recursive
	}
}
