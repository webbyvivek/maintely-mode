<?php
/**
 * Automatic maintenance mode scheduling via WP-Cron.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Scheduler.
 *
 * Watches the plugin's stored options and, whenever they change, syncs
 * two single WP-Cron events against the configured start/end date-times:
 * one that flips maintenance mode ON at the start time, and one that
 * flips it OFF at the end time. Using wp_schedule_single_event() for
 * exact moments (rather than a recurring polling interval) keeps this
 * feature precise while still using WordPress Cron only, per spec.
 *
 * If a start or end time has already passed by the time the schedule is
 * saved (e.g. the admin sets a start time in the past to begin
 * immediately), the corresponding state is applied right away instead
 * of being scheduled.
 */
class Maintely_Mode_Scheduler {

	/**
	 * Cron hook fired to turn maintenance mode ON.
	 *
	 * @var string
	 */
	const ACTIVATE_HOOK = 'maintely_mode_scheduled_activate';

	/**
	 * Cron hook fired to turn maintenance mode OFF.
	 *
	 * @var string
	 */
	const DEACTIVATE_HOOK = 'maintely_mode_scheduled_deactivate';

	/**
	 * Reentrancy guard so our own update_option() calls below don't
	 * cause the sync logic to recurse into itself.
	 *
	 * @var bool
	 */
	private static $is_syncing = false;

	/**
	 * Constructor. Self-registers this module's WordPress hooks.
	 */
	public function __construct() {
		add_action( 'update_option_' . MAINTELY_MODE_OPTION_KEY, array( $this, 'maybe_sync_schedule' ), 10, 2 );
		add_action( 'add_option_' . MAINTELY_MODE_OPTION_KEY, array( $this, 'maybe_sync_schedule_on_add' ), 10, 2 );
		add_action( self::ACTIVATE_HOOK, array( $this, 'activate_maintenance' ) );
		add_action( self::DEACTIVATE_HOOK, array( $this, 'deactivate_maintenance' ) );
	}

	/**
	 * Fires when the option is updated. Re-syncs the schedule against
	 * the freshly saved values.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function maybe_sync_schedule( $old_value, $new_value ) {
		$this->sync_schedule( $new_value );
	}

	/**
	 * Fires the first time the option is ever created (e.g. activation).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function maybe_sync_schedule_on_add( $option, $value ) {
		$this->sync_schedule( $value );
	}

	/**
	 * Clear and re-schedule the cron events based on the given options.
	 *
	 * @param mixed $raw_options The plugin's option array (possibly partial).
	 * @return void
	 */
	private function sync_schedule( $raw_options ) {

		if ( self::$is_syncing ) {
			return;
		}

		self::$is_syncing = true;

		$options = wp_parse_args(
			is_array( $raw_options ) ? $raw_options : array(),
			Maintely_Mode_Installer::get_default_options()
		);

		// Always start from a clean slate to avoid stacking duplicate events.
		$this->clear_scheduled_events();

		if ( empty( $options['schedule_enabled'] ) ) {
			self::$is_syncing = false;
			return;
		}

		$now      = time();
		$start_ts = $this->parse_local_datetime( $options['schedule_start'] );
		$end_ts   = $this->parse_local_datetime( $options['schedule_end'] );

		if ( $start_ts ) {
			if ( $start_ts > $now ) {
				wp_schedule_single_event( $start_ts, self::ACTIVATE_HOOK );
			} elseif ( ! $end_ts || $end_ts > $now ) {
				// Start already passed and we're still before the end (or there is no end) - activate now.
				$this->set_maintenance_enabled( true );
			}
		}

		if ( $end_ts ) {
			if ( $end_ts > $now ) {
				wp_schedule_single_event( $end_ts, self::DEACTIVATE_HOOK );
			} else {
				// End already passed - make sure maintenance mode is off.
				$this->set_maintenance_enabled( false );
			}
		}

		self::$is_syncing = false;
	}

	/**
	 * Remove any pending scheduled activate/deactivate events.
	 *
	 * @return void
	 */
	private function clear_scheduled_events() {
		wp_clear_scheduled_hook( self::ACTIVATE_HOOK );
		wp_clear_scheduled_hook( self::DEACTIVATE_HOOK );
	}

	/**
	 * Cron callback: turn maintenance mode ON.
	 *
	 * @return void
	 */
	public function activate_maintenance() {
		$this->set_maintenance_enabled( true );
	}

	/**
	 * Cron callback: turn maintenance mode OFF.
	 *
	 * @return void
	 */
	public function deactivate_maintenance() {
		$this->set_maintenance_enabled( false );
	}

	/**
	 * Update the stored "maintenance_enabled" flag, if it actually changes.
	 *
	 * @param bool $enabled New desired state.
	 * @return void
	 */
	private function set_maintenance_enabled( $enabled ) {

		$options = maintely_mode_get_options();

		if ( (bool) $options['maintenance_enabled'] === (bool) $enabled ) {
			return;
		}

		$options['maintenance_enabled'] = (bool) $enabled;

		update_option( MAINTELY_MODE_OPTION_KEY, $options );
	}

	/**
	 * Convert a "Y-m-d\TH:i" (or "Y-m-d H:i") value, interpreted in the
	 * site's configured timezone, into a Unix (UTC) timestamp suitable
	 * for wp_schedule_single_event().
	 *
	 * @param string $value Raw stored date-time string.
	 * @return int 0 if empty or invalid.
	 */
	private function parse_local_datetime( $value ) {

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return 0;
		}

		try {
			$date = new DateTime( $value, wp_timezone() );
			return $date->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}
}
