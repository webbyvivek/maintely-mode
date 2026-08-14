<?php
/**
 * Handles plugin activation and deactivation.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Installer.
 *
 * Responsible only for what needs to happen the moment the plugin is
 * activated or deactivated. Settings fields, maintenance logic, and
 * scheduling are handled by their own dedicated classes in later
 * phases - this class never contains feature logic, only setup/teardown.
 */
class Maintely_Mode_Installer {

	/**
	 * Runs on plugin activation.
	 *
	 * Seeds the default option array (only if it does not already
	 * exist, so re-activating never overwrites existing settings) and
	 * records the version the options were created with.
	 *
	 * @return void
	 */
	public static function activate() {

		$options = get_option( MAINTELY_MODE_OPTION_KEY );

		if ( false === $options ) {
			$options                        = self::get_default_options();
			$options['secret_access_token'] = Maintely_Mode_Bypass::generate_token();
			add_option( MAINTELY_MODE_OPTION_KEY, $options );
		} elseif ( empty( $options['secret_access_token'] ) ) {
			// Backfills a token for a site that was activated before
			// auto-generation existed, or somehow ended up with a blank one.
			$options['secret_access_token'] = Maintely_Mode_Bypass::generate_token();
			update_option( MAINTELY_MODE_OPTION_KEY, $options );
		}

		if ( false === get_option( 'maintely_mode_version' ) ) {
			add_option( 'maintely_mode_version', MAINTELY_MODE_VERSION );
		}

		// Ensure any role/capability caches WordPress may hold are fresh.
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Clears any scheduled cron events owned by the plugin and flushes
	 * rewrite rules. Deliberately does not delete any options - that is
	 * the responsibility of uninstall.php, and only runs on full removal.
	 *
	 * @return void
	 */
	public static function deactivate() {

		wp_clear_scheduled_hook( Maintely_Mode_Scheduler::ACTIVATE_HOOK );
		wp_clear_scheduled_hook( Maintely_Mode_Scheduler::DEACTIVATE_HOOK );

		flush_rewrite_rules();
	}

	/**
	 * The default values for the plugin's single settings option array.
	 *
	 * Kept centralized here so activation and the Settings API (Phase 4)
	 * share a single source of truth for defaults.
	 *
	 * @return array
	 */
	public static function get_default_options() {
		return array(
			'maintenance_enabled'   => false,
			'maintenance_title'     => __( 'We\'ll be back soon', 'maintely-mode' ),
			'maintenance_message'   => __( 'Our website is currently undergoing scheduled maintenance. Thank you for your patience.', 'maintely-mode' ),
			'logo_id'               => 0,
			'show_site_name'        => true,
			'contact_email'         => '',
			'contact_phone'         => '',
			'whatsapp_number'       => '',
			'address'               => '',
			'social_facebook'       => '',
			'social_instagram'      => '',
			'social_linkedin'       => '',
			'social_x'              => '',
			'social_youtube'        => '',
			'social_custom'         => array(),
			'secret_access_token'   => '',
			'theme_mode'            => 'light',
			'enable_particles'      => true,
			'schedule_enabled'      => false,
			'schedule_start'        => '',
			'schedule_end'          => '',
		);
	}
}
