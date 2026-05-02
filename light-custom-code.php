<?php
/**
 * Plugin Name:       Light Custom Code
 * Plugin URI:        https://slimplugins.com/light-custom-code
 * Description:       Add custom PHP snippets, responsive CSS, and head/footer code injections without a child theme. All changes survive theme updates.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Slim Plugins
 * Author URI:        https://slimplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       light-custom-code
 * Domain Path:       /languages
 *
 * @package LightCustomCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LCC_VERSION', '1.0.0' );
define( 'LCC_PLUGIN_FILE', __FILE__ );
define( 'LCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LCC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load classes that must be available very early (before plugins_loaded runs
 * the main bootstrap). Recovery and Cache are needed at priority 1.
 */
require_once LCC_PLUGIN_DIR . 'includes/class-lcc-filesystem.php';
require_once LCC_PLUGIN_DIR . 'includes/class-lcc-activator.php';
require_once LCC_PLUGIN_DIR . 'includes/class-lcc-cache.php';
require_once LCC_PLUGIN_DIR . 'includes/class-lcc-recovery.php';

/**
 * Register the recovery handler at the earliest possible moment so that
 * the ?lcc_recovery=KEY URL works even if the rest of the plugin is broken.
 */
add_action( 'plugins_loaded', array( 'LCC_Recovery', 'init' ), 1 );

/**
 * Activation hook callback.
 *
 * @since 1.0.0
 */
function lcc_activate() {
	LCC_Activator::activate();
	// Ensure a recovery key exists from the moment of activation.
	LCC_Recovery::get_key();
}
register_activation_hook( __FILE__, 'lcc_activate' );

/**
 * Deactivation hook callback.
 *
 * @since 1.0.0
 */
function lcc_deactivate() {
	// Data is intentionally kept on deactivation.
}
register_deactivation_hook( __FILE__, 'lcc_deactivate' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 *
 * @since 1.0.0
 */
function lcc_init() {
	require_once LCC_PLUGIN_DIR . 'includes/class-lcc-validator.php';
	require_once LCC_PLUGIN_DIR . 'includes/class-lcc-plugin.php';
	LCC_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'lcc_init' );
