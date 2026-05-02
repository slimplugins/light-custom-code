<?php
/**
 * PHP code syntax validator.
 *
 * Uses token_get_all() with TOKEN_PARSE flag (PHP 7.0+) to detect syntax
 * errors without executing any code. No shell_exec or proc_open required.
 *
 * @package LightCustomCode
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LCC_Validator
 *
 * @since 1.0.0
 */
class LCC_Validator {

	/**
	 * Validate PHP code for syntax errors.
	 *
	 * Wraps the user's code in a valid PHP tag so token_get_all() can parse
	 * it, then returns a WP_Error on failure or true on success.
	 *
	 * @since  1.0.0
	 * @param  string $code Raw PHP code (without opening <?php tag).
	 * @return true|WP_Error True if valid, WP_Error with message if not.
	 */
	public static function check( $code ) {
		if ( '' === trim( $code ) ) {
			return true; // Empty code is technically valid.
		}

		// Wrap the code so the tokeniser sees a complete PHP script.
		$wrapped = "<?php\n" . $code . "\n";

		// Capture and restore any existing error handler to avoid
		// third-party handlers interfering with our parse attempt.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Intentionally suppresses errors during tokenisation only; restored immediately after.
		$previous_handler = set_error_handler( '__return_null' );

		$parse_error = null;

		try {
			/*
			 * TOKEN_PARSE (value 1) instructs the tokeniser to throw a
			 * ParseError on any syntax problem. Available since PHP 7.0.
			 */
			token_get_all( $wrapped, TOKEN_PARSE );
		} catch ( ParseError $e ) {
			/*
			 * Adjust the reported line number: subtract 1 because we
			 * prepended a "<?php\n" line that the user did not write.
			 */
			$line    = max( 1, $e->getLine() - 1 );
			$message = $e->getMessage();

			// Strip the internal "in ... on line N" suffix if present.
			$message = preg_replace( '/\s+in\s+.+on\s+line\s+\d+$/i', '', $message );

			$parse_error = new WP_Error(
				'lcc_syntax_error',
				sprintf(
					/* translators: 1: error description, 2: line number */
					__( 'PHP syntax error on line %2$d: %1$s', 'light-custom-code' ),
					$message,
					$line
				)
			);
		}

		// Restore the previous error handler.
		restore_error_handler();
		if ( null !== $previous_handler ) {
			set_error_handler( $previous_handler ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			restore_error_handler(); // Balance the restore above.
		}

		if ( null !== $parse_error ) {
			return $parse_error;
		}

		return true;
	}
}
