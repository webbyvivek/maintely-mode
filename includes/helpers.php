<?php
/**
 * Global helper functions.
 *
 * Loaded once by Maintely_Mode_Loader. Contains only small, generic
 * accessors shared across modules - never feature-specific logic.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'maintely_mode_get_options' ) ) {

	/**
	 * Get the plugin's full settings array, merged with defaults.
	 *
	 * Merging with Maintely_Mode_Installer::get_default_options() guarantees
	 * every key is always present, even if a setting was added in a
	 * later plugin version than the one the site originally activated.
	 *
	 * @return array
	 */
	function maintely_mode_get_options() {
		$stored = get_option( MAINTELY_MODE_OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, Maintely_Mode_Installer::get_default_options() );
	}
}

if ( ! function_exists( 'maintely_mode_get_option' ) ) {

	/**
	 * Get a single setting value by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Value returned when the key does not exist.
	 * @return mixed
	 */
	function maintely_mode_get_option( $key, $default = false ) {
		$options = maintely_mode_get_options();

		return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}
}

if ( ! function_exists( 'maintely_mode_loader' ) ) {

	/**
	 * Get the single Maintely_Mode_Loader instance.
	 *
	 * Convenience wrapper so other modules never have to reference the
	 * Loader class name directly.
	 *
	 * @return Maintely_Mode_Loader
	 */
	function maintely_mode_loader() {
		return Maintely_Mode_Loader::instance();
	}
}

if ( ! function_exists( 'maintely_mode_asset_version' ) ) {

	/**
	 * Cache-busting version string for a plugin asset file.
	 *
	 * Uses the file's modification time when the file exists (so a
	 * browser/proxy cache is busted the moment the file changes), and
	 * falls back to the plugin version otherwise. Shared by every
	 * module that enqueues its own scoped CSS/JS, instead of each one
	 * re-implementing the same filemtime()-or-fallback check.
	 *
	 * @param string $relative_path Path relative to the assets/ folder, e.g. "css/admin.css".
	 * @return int|string
	 */
	function maintely_mode_asset_version( $relative_path ) {
		$path = MAINTELY_MODE_PLUGIN_DIR . 'assets/' . ltrim( $relative_path, '/' );
		return file_exists( $path ) ? filemtime( $path ) : MAINTELY_MODE_VERSION;
	}
}
