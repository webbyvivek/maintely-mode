<?php
/**
 * Central plugin loader.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Loader.
 *
 * Single entry point that wires the whole plugin together. It loads the
 * shared helper functions and then instantiates every feature module
 * (settings, admin UI, admin bar, AJAX, bypass, scheduler, maintenance
 * engine, frontend, assets). Each module is expected to register its
 * own WordPress hooks from inside its own constructor - the loader's
 * only job is dependency wiring and instantiation order, never feature
 * logic itself.
 *
 * Modules are instantiated defensively with class_exists() so the
 * loader already works today and automatically picks up each module
 * as it is added in a later phase, with zero changes required here.
 */
class Maintely_Mode_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var Maintely_Mode_Loader|null
	 */
	private static $instance = null;

	/**
	 * Instantiated module objects, keyed by class name.
	 *
	 * @var object[]
	 */
	private $modules = array();

	/**
	 * Ordered list of module class names to instantiate.
	 *
	 * Order matters: modules that others depend on for data (e.g.
	 * Settings) are listed first. Only classes that actually exist at
	 * runtime are instantiated, so this list can safely describe the
	 * plugin's full, final architecture before every phase is built.
	 *
	 * @var string[]
	 */
	private $module_classes = array(
		'Maintely_Mode_Settings',
		'Maintely_Mode_Admin',
		'Maintely_Mode_Assets',
		'Maintely_Mode_Admin_Bar',
		'Maintely_Mode_Bypass',
		'Maintely_Mode_Scheduler',
		'Maintely_Mode_Maintenance',
		'Maintely_Mode_Frontend',
	);

	/**
	 * Get the single loader instance, creating it on first call.
	 *
	 * @return Maintely_Mode_Loader
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Private so the loader can only ever be created through instance().
	 */
	private function __construct() {
		$this->load_helpers();
		$this->load_dependencies();
	}

	/**
	 * Prevent cloning of the singleton instance.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the singleton instance.
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Cannot unserialize a singleton.', esc_html( MAINTELY_MODE_VERSION ) );
	}

	/**
	 * Load the plugin's shared helper functions file.
	 *
	 * @return void
	 */
	private function load_helpers() {
		require_once MAINTELY_MODE_INCLUDES_DIR . 'helpers.php';
	}

	/**
	 * Instantiate every available module in dependency order.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		foreach ( $this->module_classes as $class_name ) {
			if ( class_exists( $class_name ) && ! isset( $this->modules[ $class_name ] ) ) {
				$this->modules[ $class_name ] = new $class_name();
			}
		}
	}

	/**
	 * Get an already-instantiated module by class name.
	 *
	 * Returns null if the module does not exist yet (not built in the
	 * current phase) or failed to instantiate.
	 *
	 * @param string $class_name Fully qualified module class name.
	 * @return object|null
	 */
	public function get_module( $class_name ) {
		return isset( $this->modules[ $class_name ] ) ? $this->modules[ $class_name ] : null;
	}
}
