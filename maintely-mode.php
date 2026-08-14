<?php
/**
 * Plugin Name:       Maintely Mode
 * Plugin URI:        https://github.com/webbyvivek/Maintely-Mode
 * Description:       A premium, lightweight maintenance mode plugin for WordPress with scheduling, custom branding, secret access URLs, and a beautiful maintenance page.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Virtualcode
 * Author URI:        https://virtualcode.co/
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        maintely-mode
 * Domain Path:        /languages
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------------
 * PLUGIN CONSTANTS
 * -----------------------------------------------------------------------
 * All constants are prefixed with MAINTELY_MODE_ to avoid collisions with
 * other plugins. These constants are used throughout the plugin instead
 * of hardcoded paths/URLs/versions.
 */

if ( ! defined( 'MAINTELY_MODE_VERSION' ) ) {
	define( 'MAINTELY_MODE_VERSION', '1.0.0' );
}

if ( ! defined( 'MAINTELY_MODE_PLUGIN_FILE' ) ) {
	define( 'MAINTELY_MODE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MAINTELY_MODE_PLUGIN_DIR' ) ) {
	define( 'MAINTELY_MODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MAINTELY_MODE_PLUGIN_URL' ) ) {
	define( 'MAINTELY_MODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MAINTELY_MODE_PLUGIN_BASENAME' ) ) {
	define( 'MAINTELY_MODE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'MAINTELY_MODE_INCLUDES_DIR' ) ) {
	define( 'MAINTELY_MODE_INCLUDES_DIR', MAINTELY_MODE_PLUGIN_DIR . 'includes/' );
}

if ( ! defined( 'MAINTELY_MODE_TEMPLATES_DIR' ) ) {
	define( 'MAINTELY_MODE_TEMPLATES_DIR', MAINTELY_MODE_PLUGIN_DIR . 'templates/' );
}

if ( ! defined( 'MAINTELY_MODE_ASSETS_URL' ) ) {
	define( 'MAINTELY_MODE_ASSETS_URL', MAINTELY_MODE_PLUGIN_URL . 'assets/' );
}

if ( ! defined( 'MAINTELY_MODE_OPTION_KEY' ) ) {
	define( 'MAINTELY_MODE_OPTION_KEY', 'maintely_mode_settings' );
}

/**
 * -----------------------------------------------------------------------
 * AUTOLOADER
 * -----------------------------------------------------------------------
 * Load the custom autoloader class and register it. The autoloader maps
 * class names such as Maintely_Mode_Admin to includes/class-admin.php
 * following a WordPress-friendly PSR-4-style convention.
 */
require_once MAINTELY_MODE_INCLUDES_DIR . 'class-autoloader.php';
Maintely_Mode_Autoloader::register();

/**
 * -----------------------------------------------------------------------
 * ACTIVATION / DEACTIVATION HOOKS
 * -----------------------------------------------------------------------
 * Business logic for activation/deactivation lives in the Installer
 * class so that this bootstrap file stays purely structural.
 */
register_activation_hook( MAINTELY_MODE_PLUGIN_FILE, array( 'Maintely_Mode_Installer', 'activate' ) );
register_deactivation_hook( MAINTELY_MODE_PLUGIN_FILE, array( 'Maintely_Mode_Installer', 'deactivate' ) );

/**
 * -----------------------------------------------------------------------
 * PLUGIN INITIALIZATION
 * -----------------------------------------------------------------------
 * The actual plugin bootstrapping (loading shared helpers and
 * instantiating every feature module) is delegated to the
 * Maintely_Mode_Loader class. This keeps the bootstrap file itself purely
 * structural: it never contains feature logic, only the single,
 * documented entry point other files can rely on.
 */
if ( ! function_exists( 'maintely_mode' ) ) {

	/**
	 * Boot the Maintely Mode plugin.
	 *
	 * @return Maintely_Mode_Loader|null
	 */
	function maintely_mode() {
		if ( class_exists( 'Maintely_Mode_Loader' ) ) {
			return Maintely_Mode_Loader::instance();
		}

		return null;
	}
}

add_action( 'plugins_loaded', 'maintely_mode' );
