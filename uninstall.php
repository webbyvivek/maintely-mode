<?php
/**
 * Fired when the plugin is uninstalled (deleted) from the Plugins screen.
 *
 * Removes every option this plugin created. Uploaded media (logo)
 * is intentionally left untouched, as WordPress attachments are
 * shared resources that may be used elsewhere on the site. The
 * favicon is a fixed plugin asset file, not a Media Library
 * attachment, so there is nothing to clean up for it here.
 *
 * @package Maintely_Mode
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'maintely_mode_settings' );
delete_option( 'maintely_mode_version' );

// Remove any scheduled cron events left behind, if present.
wp_clear_scheduled_hook( 'maintely_mode_scheduled_activate' );
wp_clear_scheduled_hook( 'maintely_mode_scheduled_deactivate' );
