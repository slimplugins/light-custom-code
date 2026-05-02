<?php
/**
 * Manages the PHP snippet cache file.
 *
 * Active PHP snippets are written to a single PHP file inside the uploads
 * directory and executed via include. This avoids eval() and keeps execution
 * transparent and debuggable.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Cache
 *
 * @since 1.0.0
 */
class LCC_Cache {

	/**
	 * Name of the generated cache file.
	 *
	 * @var string
	 */
	const CACHE_FILE_NAME = 'active-snippets.php';

	/**
	 * Rebuild the snippet cache file from the currently stored snippets.
	 *
	 * Should be called whenever snippets are saved, toggled, or deleted.
	 *
	 * @since  1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function rebuild() {
		LCC_Activator::create_cache_directory();

		$cache_file = self::get_cache_file_path();
		$snippets   = get_option( 'lcc_snippets', array() );

		if ( ! is_array( $snippets ) ) {
			$snippets = array();
		}

		// Filter to only active snippets, then sort by priority (ascending).
		$active = array_filter(
			$snippets,
			static function ( $s ) {
				return ! empty( $s['active'] );
			}
		);

		usort(
			$active,
			static function ( $a, $b ) {
				return (int) $a['priority'] - (int) $b['priority'];
			}
		);

		// Build the cache file contents.
		$output  = "<?php\n";
		$output .= "/**\n";
		$output .= " * Light Custom Code — PHP Snippets Cache\n";
		$output .= " * Auto-generated on " . gmdate( 'Y-m-d H:i:s' ) . " UTC. Do not edit directly.\n";
		$output .= " *\n";
		$output .= " * @package LightCustomCode\n";
		$output .= " */\n\n";
		$output .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";

		foreach ( $active as $snippet ) {
			$name = isset( $snippet['name'] ) ? $snippet['name'] : 'Unnamed Snippet';
			$code = isset( $snippet['code'] ) ? $snippet['code'] : '';

			// Strip any opening PHP tags the user might have included.
			$code = self::strip_opening_php_tag( $code );

			if ( '' === trim( $code ) ) {
				continue;
			}

			$output .= "// Snippet: " . str_replace( array( "\r", "\n" ), ' ', $name ) . "\n";
			$output .= "try {\n";

			// Indent each line of user code by one tab for readability.
			$indented = implode(
				"\n",
				array_map(
					static function ( $line ) {
						return "\t" . $line;
					},
					explode( "\n", $code )
				)
			);

			$output .= $indented . "\n";
			$output .= "} catch ( \\Throwable \$lcc_e ) {\n";
			$output .= "\tif ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {\n";
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			$output .= "\t\terror_log( 'Light Custom Code — Snippet Error [" . esc_js( $name ) . "]: ' . \$lcc_e->getMessage() );\n";
			$output .= "\t}\n";
			$output .= "}\n\n";
		}

		return LCC_Filesystem::write( $cache_file, $output );

	}

	/**
	 * Delete the cache file.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public static function delete() {
		$cache_file = self::get_cache_file_path();
		if ( file_exists( $cache_file ) ) {
			wp_delete_file( $cache_file );
		}
	}

	/**
	 * Return the absolute path to the cache file.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public static function get_cache_file_path() {
		return trailingslashit( LCC_Activator::get_cache_dir() ) . self::CACHE_FILE_NAME;
	}

	/**
	 * Check whether the cache file exists and is readable.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function exists() {
		$cache_file = self::get_cache_file_path();
		return file_exists( $cache_file ) && is_readable( $cache_file );
	}

	/**
	 * Strip an opening PHP tag from user-supplied code.
	 *
	 * Handles `<?php` (with or without trailing whitespace) and the short
	 * echo tag `<?=` (which would cause a syntax error inside our wrapper).
	 *
	 * @since  1.0.0
	 * @param  string $code Raw user code.
	 * @return string       Code without a leading PHP open tag.
	 */
	private static function strip_opening_php_tag( $code ) {
		$code = ltrim( $code );

		if ( 0 === strpos( $code, '<?php' ) ) {
			$code = ltrim( substr( $code, 5 ) );
		} elseif ( 0 === strpos( $code, '<?=' ) ) {
			// Short echo tag — convert to a regular echo statement.
			$code = 'echo ' . ltrim( substr( $code, 3 ) );
		} elseif ( 0 === strpos( $code, '<?' ) ) {
			$code = ltrim( substr( $code, 2 ) );
		}

		// Also strip a trailing closing tag to avoid "headers already sent".
		$code = rtrim( $code );
		if ( '?>' === substr( $code, -2 ) ) {
			$code = rtrim( substr( $code, 0, -2 ) );
		}

		return $code;
	}
}
