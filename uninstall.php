<?php
/**
 * Plugin uninstaller.
 *
 * WordPress calls this file automatically when the administrator deletes the
 * plugin via Plugins → Delete. It removes every trace the plugin has left in
 * the database and on the filesystem.
 *
 * What is removed
 * ───────────────
 * Database (wp_options):
 *   - lcc_snippets
 *   - lcc_head_code
 *   - lcc_footer_code
 *   - lcc_css
 *   - lcc_recovery_key
 *   - lcc_last_fatal_error
 *
 * Transients (wp_options prefixed with _transient_):
 *   All lcc_* transients for all users are removed via a direct query because
 *   delete_transient() only works for the current user's transients.
 *
 * Filesystem:
 *   - wp-content/uploads/light-custom-code/  (entire directory)
 *
 * Multisite:
 *   On a network installation each site's options and uploads directory are
 *   cleaned independently.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

// WordPress sets this constant before calling uninstall.php.
// If it is not set someone is trying to access the file directly — exit immediately.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all data for a single WordPress site (blog).
 *
 * @since  1.0.0
 * @param  int $blog_id Blog ID to clean. 0 means the current (or only) site.
 */
function lcc_uninstall_site( $blog_id = 0 ) {
	global $wpdb;

	// Switch to the target site on multisite so all API functions use the
	// correct table prefix.
	if ( $blog_id > 0 && is_multisite() ) {
		switch_to_blog( $blog_id );
	}

	// ── 1. Named options ────────────────────────────────────────────────────

	$options = array(
		'lcc_snippets',
		'lcc_head_code',
		'lcc_footer_code',
		'lcc_css',
		'lcc_recovery_key',
		'lcc_last_fatal_error',
	);

	// Note: the .running and .disabled sentinel files live inside the cache
	// directory (uploads/light-custom-code/) and are removed together with
	// it in step 3 below.

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// ── 2. Transients ────────────────────────────────────────────────────────
	// delete_transient() only removes one key at a time and requires knowing
	// the exact key. We use a direct query with a LIKE pattern to sweep up all
	// lcc_* transients (per-user and global) in one pass.

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE %s
			    OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_lcc_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_lcc_' ) . '%'
		)
	);
	// phpcs:enable

	// ── 3. Cache directory in uploads ────────────────────────────────────────

	$upload_dir = wp_upload_dir();
	$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'light-custom-code';

	if ( is_dir( $cache_dir ) ) {
		lcc_delete_directory( $cache_dir );
	}

	// Switch back when we're done with this site.
	if ( $blog_id > 0 && is_multisite() ) {
		restore_current_blog();
	}
}

/**
 * Recursively delete a directory and all its contents.
 *
 * Uses WP_Filesystem_Direct when available; falls back to SPL RecursiveIterator
 * for environments where the WP admin includes are not loaded. This is
 * intentionally self-contained so the uninstaller does not depend on any
 * plugin class that may no longer be autoloaded.
 *
 * @since  1.0.0
 * @param  string $dir Absolute path to the directory to delete.
 */
function lcc_delete_directory( $dir ) {
	// Prefer WP_Filesystem_Direct (proper WordPress way).
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		$admin_file = ABSPATH . 'wp-admin/includes/file.php';
		if ( file_exists( $admin_file ) ) {
			require_once $admin_file;
		}
	}

	if ( function_exists( 'WP_Filesystem' ) ) {
		WP_Filesystem( false, false, true );

		global $wp_filesystem;

		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			$wp_filesystem->delete( $dir, true );
			return;
		}
	}

	// Fallback: plain PHP — acceptable in an uninstall context because we are
	// not writing to the filesystem, only deleting files we created ourselves.
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}

	rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}

// ── Run the uninstaller ───────────────────────────────────────────────────────

if ( is_multisite() ) {
	// On a network install, clean every site individually.
	$site_ids = get_sites(
		array(
			'fields'     => 'ids',
			'number'     => 0, // 0 = no limit
			'spam'       => 0,
			'deleted'    => 0,
			'archived'   => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		lcc_uninstall_site( (int) $site_id );
	}
} else {
	lcc_uninstall_site();
}
