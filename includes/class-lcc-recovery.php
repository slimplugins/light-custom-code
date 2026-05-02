<?php
/**
 * Emergency recovery: safe mode and fatal-error auto-disable.
 *
 * SAFE MODE URL
 * ─────────────
 * If a snippet causes a fatal/500, the admin can visit:
 *   https://example.com/?lcc_recovery=<KEY>
 * This disables all snippets and redirects to the admin dashboard.
 * The key is a 32-char random hex string stored in wp_options.
 *
 * FATAL-ERROR AUTO-DISABLE — FILE-BASED FLAG SYSTEM
 * ──────────────────────────────────────────────────
 * Before including the snippet cache file, the frontend writes a small
 * ".running" sentinel file to the uploads directory. If execution
 * completes normally the file is deleted. The shutdown function checks:
 * if the file still exists AND error_get_last() reports a fatal error,
 * all snippets are disabled.
 *
 * Why a file instead of a database transient?
 * ────────────────────────────────────────────
 * A fatal PHP error can crash the MySQL connection (e.g. memory exhaustion,
 * host OOM killer). In that state get_transient() and set_transient() both
 * fail silently, so the old transient-based flag would miss the event and
 * the site would remain broken on the next request.
 *
 * A file in the uploads directory is written by the OS kernel, not MySQL.
 * file_exists() and unlink() work even when the database is completely
 * unavailable, making the detection reliable regardless of failure mode.
 *
 * TWO-FILE PROTOCOL
 * ─────────────────
 * .running  Written before the cache include; deleted after. Its presence
 *           during shutdown means execution never completed cleanly.
 *
 * .disabled Written by the shutdown handler when it detects a fatal error.
 *           The frontend checks for this file at the very start of
 *           execute_snippets() and skips all snippet execution when it is
 *           present. This protects the next request even if the DB write
 *           that disables the snippets in wp_options failed.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Recovery
 *
 * @since 1.0.0
 */
class LCC_Recovery {

	/**
	 * Option name that stores the recovery key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'lcc_recovery_key';

	/**
	 * File name of the "currently executing" sentinel.
	 *
	 * @var string
	 */
	const FLAG_FILE = '.running';

	/**
	 * File name of the "fatal error occurred" sentinel.
	 * Prevents snippet execution on the next request even when the DB is down.
	 *
	 * @var string
	 */
	const DISABLED_FILE = '.disabled';

	/**
	 * Option name that stores the last auto-disable reason.
	 *
	 * @var string
	 */
	const OPTION_LAST_ERROR = 'lcc_last_fatal_error';

	// ---------------------------------------------------------------------------
	// Boot
	// ---------------------------------------------------------------------------

	/**
	 * Register recovery hooks. Called very early (plugins_loaded priority 1).
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		if ( isset( $_GET['lcc_recovery'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::handle_recovery_request();
		}
	}

	// ---------------------------------------------------------------------------
	// Recovery key & URL
	// ---------------------------------------------------------------------------

	/**
	 * Retrieve (or create) the site-specific recovery key.
	 *
	 * @since  1.0.0
	 * @return string 32-character hex key.
	 */
	public static function get_key() {
		$key = get_option( self::OPTION_KEY, '' );

		if ( '' === $key || 32 !== strlen( $key ) ) {
			$key = bin2hex( random_bytes( 16 ) );
			update_option( self::OPTION_KEY, $key, false );
		}

		return $key;
	}

	/**
	 * Build the full recovery URL for display in the admin.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_url() {
		return add_query_arg( 'lcc_recovery', self::get_key(), home_url( '/' ) );
	}

	/**
	 * Validate the recovery request and disable all snippets.
	 *
	 * @since 1.0.0
	 */
	private static function handle_recovery_request() {
		$provided = isset( $_GET['lcc_recovery'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Recovery URL uses a secret key via hash_equals(), not a nonce.
			? sanitize_text_field( wp_unslash( $_GET['lcc_recovery'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
			: '';
		$stored   = get_option( self::OPTION_KEY, '' );

		if ( '' === $stored || ! hash_equals( $stored, $provided ) ) {
			return;
		}

		self::disable_all_snippets( 'Manual recovery via recovery URL.' );

		// Rotate the key so the URL cannot be reused.
		update_option( self::OPTION_KEY, bin2hex( random_bytes( 16 ) ), false );

		$admin_url = admin_url( 'admin.php?page=light-custom-code&lcc_recovered=1' );
		wp_redirect( $admin_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	// ---------------------------------------------------------------------------
	// File-based running flag
	// ---------------------------------------------------------------------------

	/**
	 * Return the absolute path to the .running sentinel file.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_flag_path() {
		return trailingslashit( LCC_Activator::get_cache_dir() ) . self::FLAG_FILE;
	}

	/**
	 * Return the absolute path to the .disabled sentinel file.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_disabled_path() {
		return trailingslashit( LCC_Activator::get_cache_dir() ) . self::DISABLED_FILE;
	}

	/**
	 * Write the .running sentinel file before the cache is included.
	 *
	 * Uses plain file_put_contents — intentionally. At this point in the
	 * request we cannot guarantee WP_Filesystem is initialised, and the
	 * entire purpose of this file is to work when the database is down.
	 * Writing a tiny sentinel does not require WP_Filesystem.
	 *
	 * @since 1.0.0
	 */
	public static function set_running_flag() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( self::get_flag_path(), (string) time(), LOCK_EX );
	}

	/**
	 * Delete the .running sentinel file after a successful cache include.
	 *
	 * @since 1.0.0
	 */
	public static function clear_running_flag() {
		$path = self::get_flag_path();
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Check whether the .disabled sentinel file is present.
	 *
	 * Called at the very start of execute_snippets() so that even if the DB
	 * write in disable_all_snippets() failed (e.g. DB was also down), the
	 * next request still skips snippet execution.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function is_disabled_by_file() {
		return file_exists( self::get_disabled_path() );
	}

	/**
	 * Remove the .disabled sentinel file and record that the admin
	 * acknowledged the situation.
	 *
	 * Called after the frontend detects and honours the .disabled file, so
	 * normal snippet execution can resume once the admin has fixed the issue
	 * and re-enabled snippets in the UI.
	 *
	 * @since 1.0.0
	 */
	public static function clear_disabled_file() {
		$path = self::get_disabled_path();
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	// ---------------------------------------------------------------------------
	// Shutdown handler
	// ---------------------------------------------------------------------------

	/**
	 * Register the shutdown function.
	 *
	 * @since 1.0.0
	 */
	public static function register_shutdown_handler() {
		register_shutdown_function( array( __CLASS__, 'shutdown_handler' ) );
	}

	/**
	 * Shutdown callback: detect fatal errors and disable snippets.
	 *
	 * Checks file_exists() — not a DB call — so this works even when MySQL
	 * is unreachable.
	 *
	 * @since 1.0.0
	 */
	public static function shutdown_handler() {
		// If the .running file is gone, execution completed cleanly — nothing to do.
		if ( ! file_exists( self::get_flag_path() ) ) {
			return;
		}

		$error       = error_get_last();
		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );

		if ( null === $error || ! in_array( $error['type'], $fatal_types, true ) ) {
			// Not a fatal error (e.g. normal shutdown after output). Clean up the flag.
			self::clear_running_flag();
			return;
		}

		// ── Fatal error detected ─────────────────────────────────────────────

		$reason = sprintf(
			'Fatal error auto-detected: %1$s in %2$s on line %3$d.',
			$error['message'],
			$error['file'],
			$error['line']
		);

		// Step 1: Write the .disabled file FIRST — this requires no DB and
		// protects the next request even if all subsequent DB writes fail.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( self::get_disabled_path(), $reason . "\n" . (string) time(), LOCK_EX );

		// Step 2: Remove the .running flag.
		self::clear_running_flag();

		// Step 3: Try to persist the disable in the database (best-effort).
		// If the DB is down this will silently fail, but the .disabled file
		// already ensures the next request is protected.
		self::disable_all_snippets( $reason );

		// Step 4: Delete the cache file so the next load starts clean.
		LCC_Cache::delete();
	}

	// ---------------------------------------------------------------------------
	// Disable helper
	// ---------------------------------------------------------------------------

	/**
	 * Set all snippets to inactive and rebuild the (now empty) cache.
	 *
	 * @since  1.0.0
	 * @param  string $reason Human-readable reason for logging.
	 */
	public static function disable_all_snippets( $reason ) {
		$snippets = get_option( 'lcc_snippets', array() );

		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		foreach ( $snippets as &$snippet ) {
			$snippet['active'] = false;
		}
		unset( $snippet );

		update_option( 'lcc_snippets', $snippets, false );
		update_option( self::OPTION_LAST_ERROR, $reason, false );

		LCC_Cache::rebuild();
	}

	/**
	 * Return the last auto-disable reason and clear it so it only shows once.
	 *
	 * @since  1.0.0
	 * @return string Empty string if none.
	 */
	public static function get_and_clear_last_error() {
		$message = get_option( self::OPTION_LAST_ERROR, '' );
		if ( '' !== $message ) {
			delete_option( self::OPTION_LAST_ERROR );
		}
		return $message;
	}
}
