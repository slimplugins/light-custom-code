<?php
/**
 * Frontend output: PHP snippet execution, CSS injection, head/footer code.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Frontend
 *
 * @since 1.0.0
 */
class LCC_Frontend {

	/**
	 * Register all hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Execute PHP snippets very early, after the theme is set up.
		add_action( 'after_setup_theme', array( $this, 'execute_snippets' ), 99 );

		// Inject head code via wp_head (priority 1 = very early).
		add_action( 'wp_head', array( $this, 'inject_head_code' ), 1 );

		// Inject footer code before </body> (priority 99 = very late).
		add_action( 'wp_footer', array( $this, 'inject_footer_code' ), 99 );

		// Enqueue custom CSS.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_css' ) );
	}

	/**
	 * Load and execute the snippet cache file.
	 *
	 * Sets a "running" flag before the include and clears it after. A
	 * registered shutdown function in LCC_Recovery detects if the flag was
	 * never cleared (i.e. a fatal error interrupted execution) and automatically
	 * disables all snippets to restore site access.
	 *
	 * @since 1.0.0
	 */
	public function execute_snippets() {
		// ── Hard stop: .disabled sentinel file present ──────────────────────
		// The shutdown handler writes this file when it detects a fatal error,
		// using only filesystem calls so it works even when MySQL is down.
		// We honour it here before any DB reads so the site stays up.
		if ( LCC_Recovery::is_disabled_by_file() ) {
			// Remove the file so snippet execution can resume after the admin
			// has fixed and re-enabled their snippets.
			LCC_Recovery::clear_disabled_file();

			// Surface a one-time admin notice so the admin knows what happened.
			if ( ! get_option( LCC_Recovery::OPTION_LAST_ERROR, '' ) ) {
				update_option(
					LCC_Recovery::OPTION_LAST_ERROR,
					'All snippets were automatically deactivated after a fatal PHP error was detected.',
					false
				);
			}

			return;
		}

		// ── Normal path ──────────────────────────────────────────────────────
		$snippets = get_option( 'lcc_snippets', array() );
		if ( empty( $snippets ) || ! is_array( $snippets ) ) {
			return;
		}

		$has_active = false;
		foreach ( $snippets as $snippet ) {
			if ( ! empty( $snippet['active'] ) ) {
				$has_active = true;
				break;
			}
		}

		if ( ! $has_active ) {
			return;
		}

		if ( ! LCC_Cache::exists() ) {
			return;
		}

		$cache_file = LCC_Cache::get_cache_file_path();

		// Register the shutdown safety net and write the .running sentinel file.
		LCC_Recovery::register_shutdown_handler();
		LCC_Recovery::set_running_flag();

		include $cache_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.IncludingFile

		// Successful include — delete the .running file so shutdown does nothing.
		LCC_Recovery::clear_running_flag();
	}

	/**
	 * Echo the head code stored in options directly into <head>.
	 *
	 * Only outputs on the public-facing frontend.
	 *
	 * @since 1.0.0
	 */
	public function inject_head_code() {
		if ( is_admin() ) {
			return;
		}

		$head_code = get_option( 'lcc_head_code', '' );
		if ( '' === trim( $head_code ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n" . $head_code . "\n";
	}

	/**
	 * Echo the footer code stored in options before </body>.
	 *
	 * Only outputs on the public-facing frontend.
	 *
	 * @since 1.0.0
	 */
	public function inject_footer_code() {
		if ( is_admin() ) {
			return;
		}

		$footer_code = get_option( 'lcc_footer_code', '' );
		if ( '' === trim( $footer_code ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "\n" . $footer_code . "\n";
	}

	/**
	 * Register custom CSS as an inline style attached to a do-nothing handle.
	 *
	 * Uses wp_add_inline_style() so the output is properly queued and can be
	 * filtered / removed by other plugins.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_custom_css() {
		$css_options = get_option( 'lcc_css', array() );

		if ( empty( $css_options ) || ! is_array( $css_options ) ) {
			return;
		}

		$global  = isset( $css_options['global'] ) ? $css_options['global'] : '';
		$desktop = isset( $css_options['desktop'] ) ? $css_options['desktop'] : '';
		$tablet  = isset( $css_options['tablet'] ) ? $css_options['tablet'] : '';
		$mobile  = isset( $css_options['mobile'] ) ? $css_options['mobile'] : '';

		$tablet_bp = isset( $css_options['tablet_breakpoint'] ) ? absint( $css_options['tablet_breakpoint'] ) : 1024;
		$mobile_bp = isset( $css_options['mobile_breakpoint'] ) ? absint( $css_options['mobile_breakpoint'] ) : 768;

		$css = '';

		if ( '' !== trim( $global ) ) {
			$css .= "/* Light Custom Code — Global */\n" . $global . "\n";
		}

		if ( '' !== trim( $desktop ) ) {
			$css .= "\n/* Light Custom Code — Desktop (min-width: " . ( $tablet_bp + 1 ) . "px) */\n";
			$css .= '@media (min-width: ' . ( $tablet_bp + 1 ) . 'px) {' . "\n" . $desktop . "\n}\n";
		}

		if ( '' !== trim( $tablet ) ) {
			$css .= "\n/* Light Custom Code — Tablet (max-width: " . $tablet_bp . "px) */\n";
			$css .= '@media (max-width: ' . $tablet_bp . 'px) {' . "\n" . $tablet . "\n}\n";
		}

		if ( '' !== trim( $mobile ) ) {
			$css .= "\n/* Light Custom Code — Mobile (max-width: " . $mobile_bp . "px) */\n";
			$css .= '@media (max-width: ' . $mobile_bp . 'px) {' . "\n" . $mobile . "\n}\n";
		}

		if ( '' === trim( $css ) ) {
			return;
		}

		wp_register_style( 'lcc-custom-css', false, array(), LCC_VERSION );
		wp_enqueue_style( 'lcc-custom-css' );
		wp_add_inline_style( 'lcc-custom-css', $css );
	}
}
