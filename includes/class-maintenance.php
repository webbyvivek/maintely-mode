<?php
/**
 * The maintenance mode engine.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Maintenance.
 *
 * Ties every other module together at request time: if maintenance
 * mode is on, and the current visitor isn't an administrator and
 * doesn't hold a valid bypass cookie (Maintely_Mode_Bypass), this class
 * renders the maintenance page (Maintely_Mode_Frontend) in place of the
 * normal front end and stops WordPress from doing anything further.
 *
 * Redirect-loop safety: this class never issues a redirect - it always
 * renders in place on the current request and exits. Nothing else in
 * the plugin redirects either: the secret-URL bypass (Maintely_Mode_Bypass)
 * applies within the same request instead of redirecting, so there is
 * no redirect anywhere in the maintenance-mode flow for a loop to form
 * around.
 */
class Maintely_Mode_Maintenance {

	/**
	 * Constructor. Self-registers this module's WordPress hooks.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_render_maintenance_page' ), 0 );
	}

	/**
	 * Render the maintenance page for this request, if applicable.
	 *
	 * @return void
	 */
	public function maybe_render_maintenance_page() {

		if ( ! $this->should_show_maintenance_page() ) {
			return;
		}

		$this->send_maintenance_headers();

		$frontend = maintely_mode_loader()->get_module( 'Maintely_Mode_Frontend' );

		if ( $frontend instanceof Maintely_Mode_Frontend ) {
			$frontend->render();
		}

		exit;
	}

	/**
	 * Decide whether the current request should see the maintenance page.
	 *
	 * @return bool
	 */
	private function should_show_maintenance_page() {

		// Never interfere with wp-admin, AJAX, cron, WP-CLI, REST, or XML-RPC requests.
		if ( is_admin() ) {
			return false;
		}

		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		) {
			return false;
		}

		if ( ! (bool) maintely_mode_get_option( 'maintenance_enabled', false ) ) {
			return false;
		}

		// Administrators always bypass maintenance mode.
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return false;
		}

		// A valid secret-URL bypass cookie also lets a visitor through.
		$bypass = maintely_mode_loader()->get_module( 'Maintely_Mode_Bypass' );

		if ( $bypass instanceof Maintely_Mode_Bypass && $bypass->has_valid_bypass_cookie() ) {
			return false;
		}

		return true;
	}

	/**
	 * Send the correct headers for a temporarily unavailable site:
	 * a 503 status with a Retry-After hint (so search engines and
	 * uptime monitors know to come back later instead of treating this
	 * as a permanent outage), and no-cache headers so the maintenance
	 * page is never cached by a proxy or browser.
	 *
	 * @return void
	 */
	private function send_maintenance_headers() {

		if ( headers_sent() ) {
			return;
		}

		status_header( 503 );
		header( 'Retry-After: 3600' );
		nocache_headers();
	}
}
