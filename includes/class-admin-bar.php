<?php
/**
 * Admin Toolbar pill button.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Admin_Bar.
 *
 * Adds a single "Maintely Mode" pill to the WordPress admin toolbar
 * (front end and wp-admin) that shows the current maintenance-mode
 * state at a glance - blue (#2563EB) when active, default styling when
 * inactive - and links straight to the plugin's Settings page. Maintenance mode
 * is managed only from Settings; this button is a status indicator and
 * shortcut, not a toggle, so there is no dropdown and no AJAX here.
 */
class Maintely_Mode_Admin_Bar {

	/**
	 * Toolbar node ID.
	 *
	 * @var string
	 */
	const NODE_ID = 'maintely-mode';

	/**
	 * Constructor. Self-registers this module's WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_node' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Whether the current user is allowed to see the button.
	 *
	 * @return bool
	 */
	private function current_user_can_view() {
		return is_user_logged_in() && current_user_can( Maintely_Mode_Admin::CAPABILITY );
	}

	/**
	 * Add the pill node: a single link straight to the Settings page.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Core admin bar instance.
	 * @return void
	 */
	public function add_toolbar_node( $wp_admin_bar ) {

		if ( ! is_admin_bar_showing() || ! $this->current_user_can_view() ) {
			return;
		}

		$enabled = (bool) maintely_mode_get_option( 'maintenance_enabled', false );

		$wp_admin_bar->add_node(
			array(
				'id'    => self::NODE_ID,
				'title' => $this->get_pill_markup( $enabled ),
				'href'  => admin_url( 'admin.php?page=' . Maintely_Mode_Admin::PAGE_SLUG ),
				'meta'  => array(
					'class' => 'maintely-mode-toolbar-link',
					'title' => __( 'Manage Maintely Mode settings', 'maintely-mode' ),
				),
			)
		);
	}

	/**
	 * Build the pill's inner markup for the current state.
	 *
	 * @param bool $enabled Current maintenance_enabled value.
	 * @return string
	 */
	private function get_pill_markup( $enabled ) {
		return sprintf(
			'<span class="maintely-mode-pill%s">%s</span>',
			$enabled ? ' is-active' : '',
			esc_html__( 'Maintely Mode', 'maintely-mode' )
		);
	}

	/**
	 * Enqueue the pill's CSS, scoped to only when it's actually visible
	 * (both front end and wp-admin, for a logged-in capable user with
	 * the admin bar showing).
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		if ( ! is_admin_bar_showing() || ! $this->current_user_can_view() ) {
			return;
		}

		wp_enqueue_style(
			'maintely-mode-admin-bar',
			MAINTELY_MODE_ASSETS_URL . 'css/admin-bar.css',
			array(),
			maintely_mode_asset_version( 'css/admin-bar.css' )
		);
	}
}
