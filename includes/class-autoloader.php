<?php
/**
 * Class autoloader.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Autoloader.
 *
 * Maps class names prefixed with "Maintely_Mode_" to their corresponding
 * file inside the includes/ directory, following the WordPress file
 * naming convention (class-{name}.php) instead of Composer's PSR-4.
 * This keeps the plugin dependency-free while still giving us clean,
 * one-class-per-file autoloading.
 */
class Maintely_Mode_Autoloader {

	/**
	 * Prefix that identifies classes owned by this plugin.
	 *
	 * @var string
	 */
	const PREFIX = 'Maintely_Mode_';

	/**
	 * Register the autoloader with PHP's SPL autoload stack.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Attempt to load a class file based on its class name.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {

		// Only handle classes belonging to this plugin.
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative_name = substr( $class_name, strlen( self::PREFIX ) );
		$file_name     = 'class-' . str_replace( '_', '-', strtolower( $relative_name ) ) . '.php';
		$file_path     = MAINTELY_MODE_INCLUDES_DIR . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}
