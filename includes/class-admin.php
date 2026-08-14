<?php
/**
 * Admin menu and settings page rendering.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Admin.
 *
 * Registers the plugin's admin menu page and renders its layout: the
 * page header, the tab navigation, and a Settings API container for
 * each tab. No settings fields are registered by this class - that is
 * the responsibility of Maintely_Mode_Settings (Phase 4) and the classes
 * built on top of it (Phases 5-10). Because the page already renders
 * through settings_fields() / do_settings_sections(), those later
 * phases will "just work" the moment they register sections/fields
 * against the page slugs defined here, with no changes needed in this
 * class.
 */
class Maintely_Mode_Admin {

	/**
	 * Menu/page slug used throughout wp-admin.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'maintely-mode';

	/**
	 * Capability required to view or manage plugin settings.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The option group name used with settings_fields().
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'maintely_mode_settings_group';

	/**
	 * Nonce action used to verify the sidebar's tab-switch links.
	 *
	 * @var string
	 */
	const TAB_NONCE_ACTION = 'maintely_mode_switch_tab';

	/**
	 * Query arg the tab-switch nonce is carried in.
	 *
	 * @var string
	 */
	const TAB_NONCE_ARG = 'maintely_mode_tab_nonce';

	/**
	 * Hook suffix returned by add_menu_page(), used to scope asset loading.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor. Registers this module's own WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . MAINTELY_MODE_PLUGIN_BASENAME, array( $this, 'add_settings_action_link' ) );
		add_filter( 'all_plugins', array( $this, 'hide_plugin_site_link' ) );
	}

	/**
	 * Register the top-level admin menu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'Maintely Mode', 'maintely-mode' ),
			__( 'Maintely Mode', 'maintely-mode' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-hammer',
			80
		);
	}

	/**
	 * Enqueue admin CSS/JS, scoped strictly to the plugin's own screen.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'maintely-mode-admin',
			MAINTELY_MODE_ASSETS_URL . 'css/admin.css',
			array(),
			maintely_mode_asset_version( 'css/admin.css' )
		);

		// Needed for the logo media uploader field (General tab).
		wp_enqueue_media();

		wp_enqueue_script(
			'maintely-mode-admin',
			MAINTELY_MODE_ASSETS_URL . 'js/admin.js',
			array( 'jquery' ),
			maintely_mode_asset_version( 'js/admin.js' ),
			true
		);

		wp_localize_script(
			'maintely-mode-admin',
			'maintelyWpAdmin',
			array(
				'copiedText'   => __( 'Copied!', 'maintely-mode' ),
				'dismissText'  => __( 'Dismiss this notice.', 'maintely-mode' ),
				'activeText'   => __( 'Active', 'maintely-mode' ),
				'disabledText' => __( 'Disabled', 'maintely-mode' ),
				'unsavedText'  => __( 'Unsaved changes', 'maintely-mode' ),
				'savedText'    => __( 'Changes saved', 'maintely-mode' ),
				// options.php redirects back here with settings-updated=true
				// after a successful save - used only to show a transient
				// "Changes saved" confirmation in the save bar on load.
				'justSaved'    => isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	/**
	 * Add a "Settings" link to this plugin's row on the Plugins page.
	 *
	 * Hooked to `plugin_action_links_{$plugin_basename}`, so WordPress
	 * only calls this for Maintely Mode's own row. The link is prepended
	 * so it appears before "Deactivate", matching the placement plugins
	 * conventionally use for their settings link.
	 *
	 * @param string[] $links Existing action links for this plugin's row.
	 * @return string[] Action links with "Settings" prepended.
	 */
	public function add_settings_action_link( $links ) {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'maintely-mode' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Hide the "Visit plugin site" link WordPress core auto-generates on
	 * the Plugins page whenever a `Plugin URI` header is present.
	 *
	 * Hooked to `all_plugins`, which filters the plugin data used solely
	 * to render the Plugins list table - it does not touch the actual
	 * plugin file header, so `Plugin URI` remains fully intact in the
	 * plugin's metadata (get_plugin_data(), the header comment itself,
	 * etc.) for anything else that reads it. Only the in-memory copy
	 * used to build that one row's title link is affected, which is
	 * enough to suppress the link without displaying it on this screen.
	 *
	 * @param array[] $plugins Plugin data for every installed plugin, keyed by basename.
	 * @return array[] Same array, with this plugin's PluginURI cleared for display purposes.
	 */
	public function hide_plugin_site_link( $plugins ) {

		if ( isset( $plugins[ MAINTELY_MODE_PLUGIN_BASENAME ] ) ) {
			$plugins[ MAINTELY_MODE_PLUGIN_BASENAME ]['PluginURI'] = '';
		}

		return $plugins;
	}

	/**
	 * The tabs shown on the settings page.
	 *
	 * The array key doubles as the Settings API "page" slug that later
	 * phases will pass to add_settings_section()/add_settings_field(),
	 * in the form "maintely_mode_{key}".
	 *
	 * @return array<string,string> Tab slug => label.
	 */
	public function get_tabs() {
		return array(
			'general'  => __( 'General', 'maintely-mode' ),
			'contact'  => __( 'Contact', 'maintely-mode' ),
			'social'   => __( 'Social Media', 'maintely-mode' ),
			'access'   => __( 'Access Control', 'maintely-mode' ),
			'design'   => __( 'Design', 'maintely-mode' ),
			'schedule' => __( 'Schedule', 'maintely-mode' ),
		);
	}

	/**
	 * Dashicon assigned to each tab for the sidebar navigation.
	 *
	 * Presentation-only lookup - has no bearing on the tab slugs used by
	 * the Settings API, and safe to change or extend without affecting
	 * functionality.
	 *
	 * @return array<string,string> Tab slug => dashicon class.
	 */
	public function get_tab_icons() {
		return array(
			'general'  => 'dashicons-admin-generic',
			'contact'  => 'dashicons-email',
			'social'   => 'dashicons-share',
			'access'   => 'dashicons-lock',
			'design'   => 'dashicons-admin-appearance',
			'schedule' => 'dashicons-calendar-alt',
		);
	}

	/**
	 * Determine the currently active tab from the request.
	 *
	 * The tab is only ever used to decide which settings section to
	 * display - it never triggers a save or any other state change,
	 * which is why it's read from $_GET on a plain link rather than
	 * posted from a form. A request that doesn't ask for a specific
	 * tab (e.g. the plugin's main menu link) has nothing to verify and
	 * simply gets the default tab. A request that does ask for one is
	 * nonce-verified: the sidebar links carry a nonce for this exact
	 * purpose (see render_page()), and a request presenting a tab
	 * without a valid nonce (missing, stale, or forged) is rejected
	 * back to the default tab rather than trusting the requested value.
	 *
	 * @return string Sanitized tab slug, guaranteed to be a known tab.
	 */
	private function get_active_tab() {
		$tabs = $this->get_tabs();

		if ( ! isset( $_GET['tab'] ) ) {
			return 'general';
		}

		$nonce = isset( $_GET[ self::TAB_NONCE_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TAB_NONCE_ARG ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::TAB_NONCE_ACTION ) ) {
			return 'general';
		}

		$requested = sanitize_key( wp_unslash( $_GET['tab'] ) );

		return array_key_exists( $requested, $tabs ) ? $requested : 'general';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'maintely-mode' ) );
		}

		$tabs           = $this->get_tabs();
		$tab_icons      = $this->get_tab_icons();
		$active_tab     = $this->get_active_tab();
		$page_slug      = 'maintely_mode_' . $active_tab;
		$maintenance_on = (bool) maintely_mode_get_option( 'maintenance_enabled', false );

		// Belt-and-suspenders: this page renders the live Secret Access
		// URL (Access tab) and other settings, so it must never be
		// served from a cache - a stale copy here could show an admin
		// a token that has already been rotated.
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		?>
		<div class="wrap maintely-mode-wrap">
			<div class="maintely-mode-header">
				<div class="maintely-mode-header-brand">
					<span class="maintely-mode-header-icon" aria-hidden="true">
						<span class="dashicons dashicons-hammer"></span>
					</span>
					<div class="maintely-mode-header-text">
						<h1 class="maintely-mode-title"><?php esc_html_e( 'Maintely Mode', 'maintely-mode' ); ?></h1>
						<p class="maintely-mode-subtitle"><?php esc_html_e( 'Maintenance mode made simple.', 'maintely-mode' ); ?></p>
					</div>
				</div>

				<div class="maintely-mode-header-status">
					<span class="maintely-mode-status-badge <?php echo esc_attr( $maintenance_on ? 'is-active' : 'is-disabled' ); ?>">
						<span class="maintely-mode-status-dot" aria-hidden="true"></span>
						<?php echo $maintenance_on ? esc_html__( 'Active', 'maintely-mode' ) : esc_html__( 'Disabled', 'maintely-mode' ); ?>
					</span>
				</div>
			</div>

			<div class="maintely-mode-body">
				<nav class="maintely-mode-sidebar" aria-label="<?php esc_attr_e( 'Maintely Mode settings sections', 'maintely-mode' ); ?>">
					<ul class="maintely-mode-sidebar-nav">
						<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
							<?php
							$tab_url = wp_nonce_url(
								add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab_slug ), admin_url( 'admin.php' ) ),
								self::TAB_NONCE_ACTION,
								self::TAB_NONCE_ARG
							);
							?>
							<li>
								<a
									href="<?php echo esc_url( $tab_url ); ?>"
									class="maintely-mode-sidebar-link<?php echo esc_attr( $active_tab === $tab_slug ? ' is-active' : '' ); ?>"
									<?php echo $active_tab === $tab_slug ? 'aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								>
									<span class="dashicons <?php echo esc_attr( isset( $tab_icons[ $tab_slug ] ) ? $tab_icons[ $tab_slug ] : 'dashicons-admin-generic' ); ?>" aria-hidden="true"></span>
									<span class="maintely-mode-sidebar-link-label"><?php echo esc_html( $tab_label ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<div class="maintely-mode-content">
					<?php
					// Printed here in the normal page flow like any
					// other WordPress admin notice - admin.js
					// (initNoticeToasts) detaches it from the DOM on
					// load and moves it into a toast container
					// appended to <body>, which is what actually
					// renders it as the top-right toast (see
					// admin.css, #maintely-mode-toast-container). That
					// move is what guarantees this can never occupy
					// space in, or visually appear inside, the page
					// header - it stops being a descendant of this
					// markup entirely. If JS is ever unavailable, it
					// simply displays here as a normal inline notice
					// instead of failing silently.
					settings_errors( MAINTELY_MODE_OPTION_KEY );

					// WordPress core caches settings errors in the
					// 'settings_errors' transient for 30 seconds so
					// they can survive the options.php redirect. We
					// just displayed it above - clear that transient
					// immediately so the exact same "Settings saved."
					// notice cannot be fetched and rendered a second
					// time by a subsequent page/tab load within that
					// window.
					delete_transient( 'settings_errors' );
					?>

					<div class="maintely-mode-tab-content">
						<form action="options.php" method="post" novalidate="novalidate">
							<?php
							settings_fields( self::OPTION_GROUP );
							do_settings_sections( $page_slug );
							?>
							<div class="maintely-mode-save-bar">
								<div class="maintely-mode-save-bar-inner">
									<span class="maintely-mode-save-status" aria-live="polite"></span>
									<button type="button" class="button maintely-mode-discard-button">
										<?php esc_html_e( 'Discard', 'maintely-mode' ); ?>
									</button>
									<?php
									submit_button(
										__( 'Save Changes', 'maintely-mode' ),
										'primary',
										'submit',
										false,
										array( 'disabled' => 'disabled' )
									);
									?>
								</div>
							</div>
						</form>

						<?php
						/**
						 * Fires after the settings form closes, for tab-specific actions
						 * that must NOT be nested inside the form above (e.g. a button
						 * that posts to admin-post.php instead of options.php - HTML
						 * does not allow nested <form> elements, and nesting one would
						 * cause the browser to submit to the outer form's action
						 * instead of the intended one).
						 *
						 * @param string $active_tab The currently active settings tab.
						 */
						do_action( 'maintely_mode_after_settings_form', $active_tab );
						?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
