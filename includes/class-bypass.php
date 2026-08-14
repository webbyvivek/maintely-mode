<?php
/**
 * Access control: secret access URL and bypass cookie handling.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Bypass.
 *
 * Owns everything related to letting a visitor who is not logged in
 * skip maintenance mode via a secret URL:
 *
 * - Generating a random access token (auto-generated on first
 *   activation via Maintely_Mode_Installer, and self-healed here if it's
 *   ever found blank).
 * - Building the shareable secret URL from that token.
 * - Detecting a valid secret-URL visit, issuing a signed bypass cookie,
 *   and applying the bypass immediately within that same request (not
 *   dependent on a redirect + cookie round-trip - see
 *   maybe_process_secret_url() for why that matters).
 * - Validating that cookie on later requests (has_valid_bypass_cookie()),
 *   for the maintenance engine to consult.
 * - Rendering and handling the "Regenerate" action on the Access tab's
 *   Secret Access URL card. That button is printed via the
 *   maintely_mode_after_settings_form hook rather than as part of the
 *   settings field itself, because it posts to admin-post.php - nested
 *   inside the Settings API's own <form action="options.php">, it
 *   would be an invalid nested <form>, and the browser would submit it
 *   to the wrong place (options.php) instead. admin.js then moves the
 *   rendered node into the URL card's heading row on load (see
 *   initRegenerateFormPlacement() in admin.js), so it only *looks*
 *   like it always lived there.
 *
 * The bypass cookie never stores the token itself. It stores an HMAC
 * of the token, so regenerating the token instantly invalidates every
 * cookie issued from a previously shared link, without needing to
 * track or revoke individual cookies.
 *
 * Regeneration correctness also depends on two things that are easy
 * to overlook because they never show up when testing on a single
 * uncached local install: the freshly written option must never be
 * read back stale from a persistent object cache, and a validated
 * secret-URL visit must never be cached by a page-cache plugin or CDN
 * (or the previous link keeps "working" from cache alone, regardless
 * of what the stored token actually is). Both are handled explicitly
 * below rather than left to chance.
 */
class Maintely_Mode_Bypass {

	/**
	 * Name of the cookie issued after a valid secret-URL visit.
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'maintely_mode_access';

	/**
	 * Query var checked for the secret token on the front end.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'maintely_access';

	/**
	 * How long the bypass cookie remains valid, in days.
	 *
	 * @var int
	 */
	const COOKIE_DAYS = 30;

	/**
	 * Length of a generated token, in characters.
	 *
	 * @var int
	 */
	const TOKEN_LENGTH = 12;

	/**
	 * Shared admin-post action / nonce action name for regenerating.
	 *
	 * @var string
	 */
	const REGENERATE_ACTION = 'maintely_mode_regenerate_token';

	/**
	 * Whether a valid secret-URL token was just validated on this exact
	 * request, set by maybe_process_secret_url(). Used only to decide
	 * whether to print the (purely cosmetic) URL-cleanup script.
	 *
	 * @var bool
	 */
	private $token_just_validated = false;

	/**
	 * Constructor. Self-registers this module's WordPress hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_process_secret_url' ), 1 );
		add_action( 'admin_post_' . self::REGENERATE_ACTION, array( $this, 'handle_regenerate_token' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_regenerated_notice' ) );
		add_action( 'maintely_mode_after_settings_form', array( $this, 'maybe_render_regenerate_button' ) );
		add_action( 'wp_footer', array( $this, 'maybe_print_url_cleanup_script' ) );
	}

	/**
	 * Generate a new, random access token.
	 *
	 * Uses a CSPRNG (random_bytes()) mapped onto an alphanumeric
	 * alphabet, so the result is URL-safe with no encoding needed and
	 * short enough to share easily, while still drawing from a proper
	 * random source rather than a predictable one.
	 *
	 * @return string A TOKEN_LENGTH-character alphanumeric token.
	 */
	public static function generate_token() {

		$alphabet    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$alphabet_len = strlen( $alphabet );
		$bytes       = random_bytes( self::TOKEN_LENGTH );
		$token       = '';

		for ( $i = 0; $i < self::TOKEN_LENGTH; $i++ ) {
			$token .= $alphabet[ ord( $bytes[ $i ] ) % $alphabet_len ];
		}

		return $token;
	}

	/**
	 * Make sure a secret access token exists, generating and saving one
	 * if it's currently blank (e.g. an install from before auto-
	 * generation existed, or one where activation didn't run cleanly).
	 *
	 * @return string The current (possibly newly generated) token.
	 */
	public static function ensure_token_exists() {

		$options = maintely_mode_get_options();

		if ( ! empty( $options['secret_access_token'] ) ) {
			return $options['secret_access_token'];
		}

		$token                           = self::generate_token();
		$options['secret_access_token']  = $token;

		update_option( MAINTELY_MODE_OPTION_KEY, $options );
		wp_cache_delete( MAINTELY_MODE_OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return $token;
	}

	/**
	 * Build the full shareable secret access URL for a token.
	 *
	 * @param string|null $token Token to use, or null to read the stored one.
	 * @return string Empty string if no token is set.
	 */
	public static function get_secret_url( $token = null ) {
		if ( null === $token ) {
			$token = maintely_mode_get_option( 'secret_access_token', '' );
		}

		if ( '' === $token ) {
			return '';
		}

		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}

	/**
	 * Derive the bypass cookie value for a given token.
	 *
	 * Using an HMAC (rather than the token itself) means the cookie
	 * automatically stops validating the moment the stored token
	 * changes, with no separate revocation list required.
	 *
	 * @param string $token The current secret access token.
	 * @return string
	 */
	private static function get_cookie_value( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Whether the current visitor holds a valid bypass cookie.
	 *
	 * Intended for the maintenance engine to consult when deciding
	 * whether to show the maintenance page.
	 *
	 * @return bool
	 */
	public function has_valid_bypass_cookie() {

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}

		$token = maintely_mode_get_option( 'secret_access_token', '' );

		if ( '' === $token ) {
			return false;
		}

		$expected = self::get_cookie_value( $token );
		$provided = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		return hash_equals( $expected, $provided );
	}

	/**
	 * Check the current front-end request for a valid secret-URL visit.
	 *
	 * On a match, issues the bypass cookie for future requests AND
	 * mirrors it into $_COOKIE immediately, so the bypass takes effect
	 * on this very first page view - it does not depend on the browser
	 * successfully round-tripping the Set-Cookie header on a follow-up
	 * request first. (The previous implementation redirected and
	 * exited immediately after setting the cookie, so the maintenance
	 * check on the *next* request was the only thing that ever
	 * consulted the cookie - if anything in between broke that round
	 * trip - a page cache serving a stale response, a reverse proxy or
	 * CDN dropping the Set-Cookie header, browser cookie settings, a
	 * cookie domain/path mismatch - the bypass would silently fail with
	 * no fallback. Applying it within the same request removes that
	 * entire class of failure.)
	 *
	 * The token stays visible in the address bar for this one page
	 * view. A small inline script (see maybe_print_url_cleanup_script())
	 * removes it from the URL via history.replaceState() once the page
	 * has rendered, purely cosmetic and never required for the bypass
	 * itself to work.
	 *
	 * @return void
	 */
	public function maybe_process_secret_url() {

		if ( is_admin() ) {
			return;
		}

		$token = maintely_mode_get_option( 'secret_access_token', '' );

		// No nonce is used (or appropriate) here: this endpoint is a
		// shareable "magic link" that must work for anonymous, logged-out
		// visitors and remain valid for as long as the token itself is
		// valid (up to COOKIE_DAYS worth of reuse) - not just for the
		// ~24-hour lifetime of a WP nonce tied to a single session. The
		// security control is the constant-time hash_equals() comparison
		// against a securely random, per-site secret token below, which
		// is the correct control for this pattern (analogous to a
		// password-reset link), not a nonce.
		if ( '' === $token || ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$provided = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $provided || ! hash_equals( $token, $provided ) ) {
			return;
		}

		// This response must never be cached. A page-cache plugin or a
		// CDN sitting in front of the site would otherwise happily cache
		// this exact bypassed page (keyed on the URL, query string
		// included), and keep serving that cached copy to anyone who
		// still has the link - even after the token has since been
		// regenerated and would no longer validate on a fresh request.
		// That is precisely how an "already revoked" secret URL can
		// keep working: not because the token is still valid, but
		// because nothing told the cache this response was one-time and
		// visitor-specific.
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		$cookie_path   = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$cookie_value  = self::get_cookie_value( $token );

		setcookie(
			self::COOKIE_NAME,
			$cookie_value,
			array(
				'expires'  => time() + ( DAY_IN_SECONDS * self::COOKIE_DAYS ),
				'path'     => $cookie_path,
				'domain'   => $cookie_domain,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Make the bypass effective immediately for this exact request -
		// has_valid_bypass_cookie() reads from $_COOKIE, which setcookie()
		// alone does not populate until the *next* request.
		$_COOKIE[ self::COOKIE_NAME ] = $cookie_value;

		$this->token_just_validated = true;
	}

	/**
	 * Print a tiny inline script that removes the secret token from the
	 * visible URL via history.replaceState(), once the real page has
	 * already rendered successfully.
	 *
	 * Purely cosmetic hygiene (keeps the token out of browser history
	 * and off the Referer header on outbound links from this page) -
	 * never required for the bypass itself, which already took effect
	 * server-side before this runs. If the active theme doesn't call
	 * wp_footer(), the token simply stays visible in the address bar;
	 * the bypass keeps working via the cookie regardless.
	 *
	 * @return void
	 */
	public function maybe_print_url_cleanup_script() {

		if ( ! $this->token_just_validated ) {
			return;
		}
		?>
		<script>
		( function () {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}
			var url = new URL( window.location.href );
			url.searchParams.delete( <?php echo wp_json_encode( self::QUERY_VAR ); ?> );
			window.history.replaceState( null, '', url.toString() );
		} )();
		</script>
		<?php
	}

	/**
	 * Render the "Regenerate" action for the Access tab's Secret
	 * Access URL card.
	 *
	 * Conceptually this belongs inside that card's heading row (see
	 * .maintely-mode-secret-url-card-actions-slot in
	 * Maintely_Mode_Settings::render_field_secret_access_url()), but it
	 * has to be *printed* here, on maintely_mode_after_settings_form,
	 * because it posts its own <form> to admin-post.php and the URL
	 * card itself sits inside the Settings API's <form
	 * action="options.php"> - a nested <form> there would be invalid
	 * HTML. admin.js's initRegenerateFormPlacement() moves this
	 * form into the card's reserved actions slot on load, so the two
	 * end up reading as one box with this action on its right.
	 *
	 * @param string $tab The currently active settings tab.
	 * @return void
	 */
	public function maybe_render_regenerate_button( $tab ) {

		if ( 'access' !== $tab || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="maintely-mode-regenerate-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::REGENERATE_ACTION ); ?>" />
			<?php wp_nonce_field( self::REGENERATE_ACTION, 'maintely_mode_regenerate_token_nonce' ); ?>
			<button type="submit" class="maintely-mode-regenerate-button" title="<?php esc_attr_e( 'Regenerate Secret URL', 'maintely-mode' ); ?>">
				<?php esc_html_e( 'Reset link', 'maintely-mode' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Handle the "Regenerate Secret URL" button on the Access tab.
	 *
	 * Registered against admin-post.php rather than the Settings API,
	 * since it performs an action (rotate the token) rather than saving
	 * submitted field values.
	 *
	 * @return void
	 */
	public function handle_regenerate_token() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'maintely-mode' ) );
		}

		check_admin_referer( self::REGENERATE_ACTION, 'maintely_mode_regenerate_token_nonce' );

		$options                        = maintely_mode_get_options();
		$previous_token                 = $options['secret_access_token'];
		$new_token                      = self::generate_token();
		$options['secret_access_token'] = $new_token;

		update_option( MAINTELY_MODE_OPTION_KEY, $options );

		// Defensively bust the options cache entry for this specific
		// option (and the combined "alloptions" cache it is folded
		// into, since this option is autoloaded). update_option()
		// already does this on a standard single-server install, but
		// on some persistent-object-cache setups (e.g. a load-balanced
		// site where each web node keeps its own non-shared cache) a
		// stale copy can otherwise keep answering get_option() calls
		// with the previous token on other requests.
		wp_cache_delete( MAINTELY_MODE_OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		/**
		 * Fires right after the secret access token has been rotated.
		 *
		 * Lets a page-cache/CDN integration purge anything it may have
		 * cached for the previous secret URL, since that URL is now
		 * permanently invalid but a cached copy of it would otherwise
		 * keep serving as if it still worked.
		 *
		 * @param string $new_token      The newly generated token.
		 * @param string $previous_token The token that was just replaced.
		 */
		do_action( 'maintely_mode_token_regenerated', $new_token, $previous_token );

		// Make sure this redirect response itself is never cached -
		// otherwise a shared/proxy cache could serve this exact
		// "token just regenerated" response to a different request.
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		wp_safe_redirect(
			wp_nonce_url(
				add_query_arg(
					array(
						'page'                             => Maintely_Mode_Admin::PAGE_SLUG,
						'tab'                              => 'access',
						'maintely_mode_token_regenerated'  => '1',
					),
					admin_url( 'admin.php' )
				),
				Maintely_Mode_Admin::TAB_NONCE_ACTION,
				Maintely_Mode_Admin::TAB_NONCE_ARG
			)
		);
		exit;
	}

	/**
	 * Show a success notice after the token has just been regenerated.
	 *
	 * @return void
	 */
	public function maybe_render_regenerated_notice() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['maintely_mode_token_regenerated'] ) || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( Maintely_Mode_Admin::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'A new secret access URL has been generated. Previously shared links no longer work.', 'maintely-mode' )
		);
	}
}
