<?php
/**
 * Settings API registration and validation.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Settings.
 *
 * Registers the single plugin option with the WordPress Settings API
 * and adds one settings section per admin tab (matching the page slugs
 * Maintely_Mode_Admin already calls do_settings_sections() against), plus
 * the sanitize callback that validates everything written to that
 * option. The General tab's fields (Phase 5) are registered and
 * rendered here; the remaining tabs (Phases 6-10) will register their
 * own add_settings_field() calls against the same section IDs and page
 * slugs already declared below, requiring no changes to this file.
 *
 * The plugin stores every setting inside a single serialized option
 * (MAINTELY_MODE_OPTION_KEY), but each tab is submitted as its own HTML
 * form. To avoid one tab's submission wiping out another tab's saved
 * values, every section renders a hidden "active tab" marker and the
 * sanitize callback only touches the keys that belong to the submitted
 * tab, merging them over the previously stored values for every other
 * key.
 */
class Maintely_Mode_Settings {

	/**
	 * Constructor. Self-registers this module's WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Register the option and one settings section per tab.
	 *
	 * @return void
	 */
	public function register() {

		register_setting(
			Maintely_Mode_Admin::OPTION_GROUP,
			MAINTELY_MODE_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Maintely_Mode_Installer::get_default_options(),
				'show_in_rest'      => false,
			)
		);

		foreach ( array_keys( $this->get_field_schema() ) as $tab_slug ) {
			add_settings_section(
				$this->get_section_id( $tab_slug ),
				'',
				array( $this, 'render_section_marker' ),
				'maintely_mode_' . $tab_slug
			);
		}

		$this->register_general_fields();
		$this->register_contact_fields();
		$this->register_social_fields();
		$this->register_access_fields();
		$this->register_design_fields();
		$this->register_schedule_fields();
	}

	/**
	 * Register the General tab's settings fields.
	 *
	 * @return void
	 */
	private function register_general_fields() {

		$page = 'maintely_mode_general';

		// Two extra sections purely for visual grouping - the base
		// "general_section" (added in register(), used by every tab)
		// keeps rendering the hidden active-tab marker and the
		// Maintenance Mode card; these two split the remaining fields
		// into their own headed, separately-boxed groups.
		add_settings_section(
			'maintely_mode_general_content_section',
			'',
			array( $this, 'render_general_content_section_intro' ),
			$page
		);

		add_settings_section(
			'maintely_mode_general_branding_section',
			'',
			array( $this, 'render_general_branding_section_intro' ),
			$page
		);

		$content_section  = 'maintely_mode_general_content_section';
		$branding_section = 'maintely_mode_general_branding_section';

		add_settings_field(
			'maintely_mode_maintenance_title',
			__( 'Maintenance Title', 'maintely-mode' ),
			array( $this, 'render_field_maintenance_title' ),
			$page,
			$content_section,
			array(
				'label_for' => 'maintely_mode_maintenance_title',
				'class'     => 'maintely-mode-title-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_maintenance_message',
			__( 'Maintenance Message', 'maintely-mode' ),
			array( $this, 'render_field_maintenance_message' ),
			$page,
			$content_section,
			array(
				'label_for' => 'maintely_mode_maintenance_message',
				'class'     => 'maintely-mode-message-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_logo',
			__( 'Logo', 'maintely-mode' ),
			array( $this, 'render_field_logo' ),
			$page,
			$branding_section
		);

		add_settings_field(
			'maintely_mode_show_site_name',
			__( 'Website Name', 'maintely-mode' ),
			array( $this, 'render_field_show_site_name' ),
			$page,
			$branding_section
		);
	}

	/**
	 * Section intro: "Content" card heading/description (General tab).
	 *
	 * @return void
	 */
	public function render_general_content_section_intro() {
		?>
		<div class="maintely-mode-section-card-header">
			<h2 class="maintely-mode-section-card-title"><?php esc_html_e( 'Content', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-section-card-description">
				<?php esc_html_e( 'The heading and message shown to visitors on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section intro: "Branding" card heading/description (General tab).
	 *
	 * @return void
	 */
	public function render_general_branding_section_intro() {
		?>
		<div class="maintely-mode-section-card-header">
			<h2 class="maintely-mode-section-card-title"><?php esc_html_e( 'Branding', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-section-card-description">
				<?php esc_html_e( 'Logo and site name displayed on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the Contact tab's settings fields.
	 *
	 * @return void
	 */
	private function register_contact_fields() {

		$page    = 'maintely_mode_contact';
		$section = $this->get_section_id( 'contact' );

		add_settings_field(
			'maintely_mode_contact_email',
			__( 'Contact Email', 'maintely-mode' ),
			array( $this, 'render_field_contact_email' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_contact_email',
				'class'     => 'maintely-mode-contact-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_contact_phone',
			__( 'Phone Number', 'maintely-mode' ),
			array( $this, 'render_field_contact_phone' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_contact_phone',
				'class'     => 'maintely-mode-contact-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_whatsapp_number',
			__( 'WhatsApp Number', 'maintely-mode' ),
			array( $this, 'render_field_whatsapp_number' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_whatsapp_number',
				'class'     => 'maintely-mode-contact-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_address',
			__( 'Address', 'maintely-mode' ),
			array( $this, 'render_field_address' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_address',
				'class'     => 'maintely-mode-contact-field-row',
			)
		);
	}

	/**
	 * Register the Social Media tab's settings fields.
	 *
	 * @return void
	 */
	private function register_social_fields() {

		$page    = 'maintely_mode_social';
		$section = $this->get_section_id( 'social' );

		add_settings_field(
			'maintely_mode_social_instagram',
			__( 'Instagram', 'maintely-mode' ),
			array( $this, 'render_field_social_instagram' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_social_instagram',
				'class'     => 'maintely-mode-social-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_social_facebook',
			__( 'Facebook', 'maintely-mode' ),
			array( $this, 'render_field_social_facebook' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_social_facebook',
				'class'     => 'maintely-mode-social-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_social_linkedin',
			__( 'LinkedIn', 'maintely-mode' ),
			array( $this, 'render_field_social_linkedin' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_social_linkedin',
				'class'     => 'maintely-mode-social-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_social_x',
			__( 'Twitter', 'maintely-mode' ),
			array( $this, 'render_field_social_x' ),
			$page,
			$section,
			array(
				'label_for' => 'maintely_mode_social_x',
				'class'     => 'maintely-mode-social-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_social_custom',
			__( 'Custom Social Links', 'maintely-mode' ),
			array( $this, 'render_field_social_custom' ),
			$page,
			$section,
			array(
				'class' => 'maintely-mode-social-field-row maintely-mode-social-custom-field-row',
			)
		);
	}

	/**
	 * Register the Access tab's settings fields.
	 *
	 * @return void
	 */
	private function register_access_fields() {

		add_settings_field(
			'maintely_mode_secret_access_url',
			__( 'Secret Access URL', 'maintely-mode' ),
			array( $this, 'render_field_secret_access_url' ),
			'maintely_mode_access',
			$this->get_section_id( 'access' ),
			array(
				'class' => 'maintely-mode-access-field-row',
			)
		);
	}

	/**
	 * Register the Design tab's settings fields.
	 *
	 * @return void
	 */
	private function register_design_fields() {

		$page    = 'maintely_mode_design';
		$section = $this->get_section_id( 'design' );

		add_settings_field(
			'maintely_mode_theme_mode',
			__( 'Theme', 'maintely-mode' ),
			array( $this, 'render_field_theme_mode' ),
			$page,
			$section,
			array(
				'class' => 'maintely-mode-design-field-row',
			)
		);

		add_settings_field(
			'maintely_mode_enable_particles',
			__( 'Particle Background', 'maintely-mode' ),
			array( $this, 'render_field_enable_particles' ),
			$page,
			$section,
			array(
				'class' => 'maintely-mode-design-field-row',
			)
		);
	}

	/**
	 * Register the Schedule tab's settings fields.
	 *
	 * @return void
	 */
	private function register_schedule_fields() {

		$page    = 'maintely_mode_schedule';
		$section = $this->get_section_id( 'schedule' );

		add_settings_field(
			'maintely_mode_schedule_enabled',
			'',
			array( $this, 'render_field_schedule_enabled' ),
			$page,
			$section,
			array( 'class' => 'maintely-mode-schedule-field-row maintely-mode-schedule-toggle-row' )
		);

		add_settings_field(
			'maintely_mode_schedule_window',
			'',
			array( $this, 'render_field_schedule_window' ),
			$page,
			$section,
			array( 'class' => 'maintely-mode-schedule-field-row maintely-mode-schedule-window-row' )
		);
	}

	/**
	 * Render the Maintenance Mode status card.
	 *
	 * Called directly from render_section_marker() for the General tab,
	 * before do_settings_sections() opens the fields table - so the
	 * card is a plain full-width block, not a table row, and lines up
	 * with the width of the settings table beneath it. The checkbox
	 * inside still uses the same id/name/value as before, so it
	 * submits and saves exactly like any other field on this form.
	 *
	 * @return void
	 */
	public function render_maintenance_card() {
		$enabled = (bool) maintely_mode_get_option( 'maintenance_enabled', false );
		?>
		<div class="maintely-mode-maintenance-card <?php echo esc_attr( $enabled ? 'is-active' : 'is-disabled' ); ?>">
			<div class="maintely-mode-maintenance-card-icon" aria-hidden="true">
				<span class="dashicons dashicons-hammer"></span>
			</div>

			<div class="maintely-mode-maintenance-card-body">
				<div class="maintely-mode-maintenance-card-heading">
					<h3 class="maintely-mode-maintenance-card-title"><?php esc_html_e( 'Maintenance Mode', 'maintely-mode' ); ?></h3>
					<span class="maintely-mode-maintenance-card-status">
						<span class="maintely-mode-maintenance-card-status-dot" aria-hidden="true"></span>
						<span class="maintely-mode-maintenance-card-status-text"><?php echo $enabled ? esc_html__( 'Active', 'maintely-mode' ) : esc_html__( 'Disabled', 'maintely-mode' ); ?></span>
					</span>
				</div>
				<p class="maintely-mode-maintenance-card-description">
					<?php esc_html_e( 'Put the site into maintenance mode for all visitors. Administrators are always able to browse the site normally while this is enabled.', 'maintely-mode' ); ?>
				</p>
			</div>

			<div class="maintely-mode-maintenance-card-control">
				<label class="maintely-mode-toggle" for="maintely_mode_maintenance_enabled">
					<input
						type="checkbox"
						id="maintely_mode_maintenance_enabled"
						name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[maintenance_enabled]"
						value="1"
						<?php checked( true, $enabled ); ?>
					/>
					<span class="maintely-mode-toggle-track" aria-hidden="true">
						<span class="maintely-mode-toggle-thumb"></span>
					</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Toggle maintenance mode', 'maintely-mode' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render: Maintenance Title text field.
	 *
	 * @return void
	 */
	public function render_field_maintenance_title() {
		$value = maintely_mode_get_option( 'maintenance_title', '' );
		?>
		<div class="maintely-mode-title-field">
			<input
				type="text"
				id="maintely_mode_maintenance_title"
				class="regular-text maintely-mode-title-input"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[maintenance_title]"
				value="<?php echo esc_attr( $value ); ?>"
			/>
			<p class="description">
				<?php esc_html_e( 'The main heading shown on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Maintenance Message textarea.
	 *
	 * @return void
	 */
	public function render_field_maintenance_message() {
		$value = maintely_mode_get_option( 'maintenance_message', '' );
		?>
		<div class="maintely-mode-message-field">
			<textarea
				id="maintely_mode_maintenance_message"
				class="large-text maintely-mode-message-input"
				rows="6"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[maintenance_message]"
			><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Shown beneath the title. Plain text only.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Logo media uploader.
	 *
	 * Uses a compact "card" markup, with the same field/value/preview/
	 * upload/remove class names the generic binding in
	 * assets/js/admin.js (initMediaField) expects, so it's powered by
	 * that shared script with no JS changes required. The stored
	 * option, name attribute, and sanitization are unchanged.
	 *
	 * @return void
	 */
	public function render_field_logo() {
		$attachment_id = (int) maintely_mode_get_option( 'logo_id', 0 );
		$image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		$has_image     = '' !== $image_url;
		$visible       = $has_image ? ' is-visible' : '';
		?>
		<div class="maintely-mode-media-field maintely-mode-logo-field" data-field="logo_id">
			<input
				type="hidden"
				class="maintely-mode-media-value"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[logo_id]"
				value="<?php echo esc_attr( $attachment_id ); ?>"
			/>
			<div class="maintely-mode-logo-card<?php echo esc_attr( $has_image ? ' has-image' : '' ); ?>">
				<div class="maintely-mode-media-preview-wrap">
					<img
						class="maintely-mode-media-preview<?php echo esc_attr( $visible ); ?>"
						src="<?php echo esc_url( $image_url ); ?>"
						alt=""
					/>
					<div class="maintely-mode-logo-empty">
						<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
						<span class="maintely-mode-logo-empty-text"><?php esc_html_e( 'No logo uploaded', 'maintely-mode' ); ?></span>
					</div>
				</div>
				<div class="maintely-mode-logo-actions">
					<button
						type="button"
						class="button button-primary maintely-mode-media-upload"
						data-title="<?php esc_attr_e( 'Select Logo', 'maintely-mode' ); ?>"
						data-button-text="<?php esc_attr_e( 'Use this logo', 'maintely-mode' ); ?>"
						data-upload-text="<?php esc_attr_e( 'Upload Logo', 'maintely-mode' ); ?>"
						data-replace-text="<?php esc_attr_e( 'Replace Logo', 'maintely-mode' ); ?>"
					><?php echo esc_html( $has_image ? __( 'Replace Logo', 'maintely-mode' ) : __( 'Upload Logo', 'maintely-mode' ) ); ?></button>
					<button type="button" class="button maintely-mode-media-remove<?php echo esc_attr( $visible ); ?>">
						<?php esc_html_e( 'Remove', 'maintely-mode' ); ?>
					</button>
				</div>
			</div>
			<p class="description">
				<?php esc_html_e( 'Displayed at the top of the maintenance page. A transparent PNG or SVG works best.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: "Display Website Name" checkbox.
	 *
	 * @return void
	 */
	public function render_field_show_site_name() {
		$enabled = (bool) maintely_mode_get_option( 'show_site_name', true );
		?>
		<div class="maintely-mode-site-name-field">
			<div class="maintely-mode-site-name-row">
				<label class="maintely-mode-toggle" for="maintely_mode_show_site_name">
					<input
						type="checkbox"
						id="maintely_mode_show_site_name"
						name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[show_site_name]"
						value="1"
						<?php checked( true, $enabled ); ?>
					/>
					<span class="maintely-mode-toggle-track" aria-hidden="true">
						<span class="maintely-mode-toggle-thumb"></span>
					</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Toggle showing the website name', 'maintely-mode' ); ?></span>
				</label>
				<span class="maintely-mode-site-name-label"><?php esc_html_e( 'Show website name', 'maintely-mode' ); ?></span>
				<span class="maintely-mode-site-name-status <?php echo esc_attr( $enabled ? 'is-on' : 'is-off' ); ?>">
					<?php echo $enabled ? esc_html__( 'On', 'maintely-mode' ) : esc_html__( 'Off', 'maintely-mode' ); ?>
				</span>
			</div>
			<p class="description">
				<?php
				printf(
					/* translators: %s: current site name */
					esc_html__( 'When enabled, your site title "%s" is shown on the maintenance page.', 'maintely-mode' ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Contact Email field.
	 *
	 * @return void
	 */
	public function render_field_contact_email() {
		$value = maintely_mode_get_option( 'contact_email', '' );
		?>
		<div class="maintely-mode-contact-field">
			<input
				type="email"
				id="maintely_mode_contact_email"
				class="regular-text maintely-mode-contact-input"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[contact_email]"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="hello@example.com"
			/>
			<p class="description">
				<?php esc_html_e( 'Shown on the maintenance page so visitors can reach you.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Phone Number field.
	 *
	 * @return void
	 */
	public function render_field_contact_phone() {
		$value = maintely_mode_get_option( 'contact_phone', '' );
		?>
		<div class="maintely-mode-contact-field">
			<input
				type="tel"
				id="maintely_mode_contact_phone"
				class="regular-text maintely-mode-contact-input"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[contact_phone]"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="+1 (555) 123-4567"
			/>
			<p class="description">
				<?php esc_html_e( 'Optional. Displayed as a clickable phone number on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: WhatsApp Number field.
	 *
	 * @return void
	 */
	public function render_field_whatsapp_number() {
		$value = maintely_mode_get_option( 'whatsapp_number', '' );
		?>
		<div class="maintely-mode-contact-field">
			<input
				type="tel"
				id="maintely_mode_whatsapp_number"
				class="regular-text maintely-mode-contact-input"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[whatsapp_number]"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="15551234567"
			/>
			<p class="description">
				<?php esc_html_e( 'Enter the number in international format, digits only (no "+", spaces, or dashes). Used to build a WhatsApp click-to-chat button on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Address field.
	 *
	 * @return void
	 */
	public function render_field_address() {
		$value = maintely_mode_get_option( 'address', '' );
		?>
		<div class="maintely-mode-contact-field">
			<textarea
				id="maintely_mode_address"
				class="large-text maintely-mode-contact-textarea"
				rows="3"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[address]"
			><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Your business or office address. Shown alongside your other contact details on the maintenance page.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Facebook URL field.
	 *
	 * @return void
	 */
	public function render_field_social_facebook() {
		$this->render_social_url_field( 'social_facebook', 'https://facebook.com/yourpage', 'maintely_mode_social_facebook' );
	}

	/**
	 * Render: Instagram URL field.
	 *
	 * @return void
	 */
	public function render_field_social_instagram() {
		$this->render_social_url_field( 'social_instagram', 'https://instagram.com/yourpage', 'maintely_mode_social_instagram' );
	}

	/**
	 * Render: LinkedIn URL field.
	 *
	 * @return void
	 */
	public function render_field_social_linkedin() {
		$this->render_social_url_field( 'social_linkedin', 'https://linkedin.com/company/yourpage', 'maintely_mode_social_linkedin' );
	}

	/**
	 * Render: Twitter URL field.
	 *
	 * @return void
	 */
	public function render_field_social_x() {
		$this->render_social_url_field( 'social_x', 'https://x.com/yourpage', 'maintely_mode_social_x' );
	}

	/**
	 * Shared markup for the fixed social-network URL fields.
	 *
	 * The field keeps the exact same input name, id, value, and
	 * placeholder as before - only a modern input wrapper (styled
	 * entirely via CSS) has been added around it, so nothing about
	 * what gets submitted or saved changes.
	 *
	 * @param string $option_key  The option array key this field controls.
	 * @param string $placeholder Placeholder URL shown when empty.
	 * @param string $field_id    The id attribute matching this field's label_for.
	 * @return void
	 */
	private function render_social_url_field( $option_key, $placeholder, $field_id = '' ) {
		$value = maintely_mode_get_option( $option_key, '' );
		?>
		<div class="maintely-mode-social-input-group">
			<input
				type="url"
				id="<?php echo esc_attr( $field_id ); ?>"
				class="regular-text maintely-mode-social-input"
				name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[<?php echo esc_attr( $option_key ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
			/>
		</div>
		<?php
	}

	/**
	 * Render: unlimited custom social links (repeatable label + URL rows).
	 *
	 * Existing rows are rendered server-side; the "Add Social Link" /
	 * "Remove" interactions are handled client-side by the generic
	 * repeater binder in assets/js/admin.js, which reads its
	 * configuration from this container's data-* attributes so no
	 * inline JS or translated strings are hardcoded in the script file.
	 *
	 * @return void
	 */
	public function render_field_social_custom() {
		$rows = maintely_mode_get_option( 'social_custom', array() );

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$name_prefix = MAINTELY_MODE_OPTION_KEY . '[social_custom]';
		?>
		<div
			class="maintely-mode-repeater maintely-mode-social-repeater"
			data-name-prefix="<?php echo esc_attr( $name_prefix ); ?>"
			data-label-placeholder="<?php esc_attr_e( 'Label (e.g. Discord)', 'maintely-mode' ); ?>"
			data-url-placeholder="<?php esc_attr_e( 'https://...', 'maintely-mode' ); ?>"
			data-remove-text="<?php esc_attr_e( 'Remove', 'maintely-mode' ); ?>"
		>
			<div class="maintely-mode-repeater-rows">
				<?php foreach ( array_values( $rows ) as $index => $row ) : ?>
					<?php
					$label = isset( $row['label'] ) ? $row['label'] : '';
					$url   = isset( $row['url'] ) ? $row['url'] : '';
					?>
					<div class="maintely-mode-repeater-row">
						<input
							type="text"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Label (e.g. Discord)', 'maintely-mode' ); ?>"
							name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][label]"
							value="<?php echo esc_attr( $label ); ?>"
						/>
						<input
							type="url"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'https://...', 'maintely-mode' ); ?>"
							name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $index ); ?>][url]"
							value="<?php echo esc_attr( $url ); ?>"
						/>
						<button type="button" class="button maintely-mode-repeater-remove">
							<?php esc_html_e( 'Remove', 'maintely-mode' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button button-secondary maintely-mode-repeater-add">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add Social Link', 'maintely-mode' ); ?>
				</button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Add any number of additional platforms not listed above.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render: Secret Access URL display (read-only).
	 *
	 * This field is read-only by design - the token is generated by
	 * Maintely_Mode_Bypass::generate_token(), never typed by the admin,
	 * so there is nothing here for the main settings form to submit or
	 * for sanitize() to validate. The "Regenerate" action rendered on
	 * the right of this card's heading is a separate <form> (posting
	 * to admin-post.php) that Maintely_Mode_Bypass prints via the
	 * maintely_mode_after_settings_form hook, outside this settings
	 * <form> entirely - a nested <form> here would be invalid HTML -
	 * then relocates into the reserved
	 * .maintely-mode-secret-url-card-actions-slot on the right of this
	 * heading row via initRegenerateFormPlacement() in admin.js, so it
	 * visually lives inside this same box.
	 *
	 * @return void
	 */
	public function render_field_secret_access_url() {
		// Self-heals a blank token (e.g. an install from before this
		// field existed) so this never shows "not generated yet".
		$token      = Maintely_Mode_Bypass::ensure_token_exists();
		$secret_url = Maintely_Mode_Bypass::get_secret_url( $token );
		?>
		<div class="maintely-mode-access-url-card">
			<div class="maintely-mode-access-url-card-heading">
				<div class="maintely-mode-access-url-card-heading-text">
					<p class="maintely-mode-access-url-label"><?php esc_html_e( 'Your Secret Access URL', 'maintely-mode' ); ?></p>
					<p class="maintely-mode-access-url-sublabel"><?php esc_html_e( 'Share this link to let someone bypass maintenance mode without logging in.', 'maintely-mode' ); ?></p>
				</div>
				<span class="maintely-mode-secret-url-card-actions-slot"></span>
			</div>

			<div class="maintely-mode-access-url-row">
				<input
					type="text"
					id="maintely-mode-secret-url-field"
					class="regular-text maintely-mode-secret-url"
					readonly="readonly"
					autocomplete="off"
					value="<?php echo esc_url( $secret_url ); ?>"
				/>
				<button type="button" class="maintely-mode-access-copy-button" data-copy-target="maintely-mode-secret-url-field" aria-label="<?php esc_attr_e( 'Copy', 'maintely-mode' ); ?>" title="<?php esc_attr_e( 'Copy', 'maintely-mode' ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
				</button>
			</div>
		</div>

		<div class="maintely-mode-access-notice">
			<span class="maintely-mode-access-notice-icon" aria-hidden="true">
				<span class="dashicons dashicons-shield-alt"></span>
			</span>
			<p>
				<?php esc_html_e( 'Anyone who visits this URL bypasses maintenance mode, even without logging in - treat it like a password. Use the "Regenerate" action above to instantly revoke previously shared links.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
		/*
		 * The Copy button reuses the exact same click-to-copy behaviour
		 * already wired up by initSecretUrlCopy() in admin.js (which
		 * binds to .maintely-mode-secret-url) - this just forwards a
		 * click on the button to a click on that same input, rather
		 * than duplicating the clipboard logic here or modifying the
		 * shared admin.js file.
		 */
		?>
		<script>
		( function () {
			var btn = document.querySelector( '.maintely-mode-access-copy-button[data-copy-target="maintely-mode-secret-url-field"]' );
			var field = document.getElementById( 'maintely-mode-secret-url-field' );
			if ( btn && field ) {
				btn.addEventListener( 'click', function () {
					field.dispatchEvent( new Event( 'click' ) );
				} );
			}
		} )();
		</script>
		<?php
	}

	/**
	 * Render: Theme Mode (Light / Dark) selector.
	 *
	 * @return void
	 */
	public function render_field_theme_mode() {
		$current = maintely_mode_get_option( 'theme_mode', 'light' );
		$options = array(
			'light' => __( 'Light', 'maintely-mode' ),
			'dark'  => __( 'Dark', 'maintely-mode' ),
		);
		?>
		<fieldset class="maintely-mode-theme-choice">
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Theme', 'maintely-mode' ); ?>
			</legend>
			<?php foreach ( $options as $value => $label ) : ?>
				<label class="maintely-mode-theme-option maintely-mode-theme-option--<?php echo esc_attr( $value ); ?>">
					<input
						type="radio"
						name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[theme_mode]"
						value="<?php echo esc_attr( $value ); ?>"
						<?php checked( $current, $value ); ?>
					/>
					<span class="maintely-mode-theme-option-preview" aria-hidden="true">
						<span class="maintely-mode-theme-option-preview-bar"></span>
						<span class="maintely-mode-theme-option-preview-line"></span>
						<span class="maintely-mode-theme-option-preview-line maintely-mode-theme-option-preview-line--short"></span>
					</span>
					<span class="maintely-mode-theme-option-meta">
						<span class="maintely-mode-theme-option-label"><?php echo esc_html( $label ); ?></span>
						<span class="maintely-mode-theme-option-check" aria-hidden="true">
							<span class="dashicons dashicons-yes-alt"></span>
						</span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'Controls the color scheme of the maintenance page.', 'maintely-mode' ); ?>
		</p>
		<?php
	}

	/**
	 * Render: Particle Background toggle.
	 *
	 * @return void
	 */
	public function render_field_enable_particles() {
		$enabled  = (bool) maintely_mode_get_option( 'enable_particles', true );
		$field_id = 'maintely_mode_enable_particles';
		?>
		<div class="maintely-mode-design-toggle-row">
			<label class="maintely-mode-toggle" for="<?php echo esc_attr( $field_id ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[enable_particles]"
					value="1"
					<?php checked( true, $enabled ); ?>
				/>
				<span class="maintely-mode-toggle-track" aria-hidden="true">
					<span class="maintely-mode-toggle-thumb"></span>
				</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Toggle particle background', 'maintely-mode' ); ?></span>
			</label>
			<label for="<?php echo esc_attr( $field_id ); ?>" class="maintely-mode-design-toggle-label">
				<?php esc_html_e( 'Show an animated particle background on the maintenance page.', 'maintely-mode' ); ?>
			</label>
		</div>
		<p class="description">
			<?php esc_html_e( 'Rendered with lightweight vanilla JavaScript - no external libraries.', 'maintely-mode' ); ?>
		</p>
		<?php
	}

	/**
	 * Render: "Automatic Scheduling" enable toggle.
	 *
	 * A self-contained status card - icon, title, status pill, helper
	 * copy, and the toggle switch itself - mirroring the Maintenance
	 * Mode card's markup pattern (General tab) so the interaction feels
	 * consistent, but scoped entirely under .maintely-mode-schedule-*
	 * classes so restyling it cannot affect that other card.
	 *
	 * @return void
	 */
	public function render_field_schedule_enabled() {
		$enabled  = (bool) maintely_mode_get_option( 'schedule_enabled', false );
		$field_id = 'maintely_mode_schedule_enabled';
		?>
		<div class="maintely-mode-schedule-toggle-card<?php echo esc_attr( $enabled ? ' is-active' : '' ); ?>">
			<div class="maintely-mode-schedule-toggle-icon" aria-hidden="true">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="maintely-mode-schedule-toggle-body">
				<div class="maintely-mode-schedule-toggle-heading">
					<label class="maintely-mode-schedule-toggle-title" for="<?php echo esc_attr( $field_id ); ?>">
						<?php esc_html_e( 'Automatic Scheduling', 'maintely-mode' ); ?>
					</label>
					<span class="maintely-mode-schedule-toggle-status">
						<span class="maintely-mode-schedule-toggle-status-dot" aria-hidden="true"></span>
						<span class="maintely-mode-schedule-toggle-status-text">
							<?php echo $enabled ? esc_html__( 'Active', 'maintely-mode' ) : esc_html__( 'Disabled', 'maintely-mode' ); ?>
						</span>
					</span>
				</div>
				<p class="maintely-mode-schedule-toggle-description">
					<?php esc_html_e( 'Automatically enable and disable maintenance mode on a schedule, using the start and end times below.', 'maintely-mode' ); ?>
				</p>
			</div>
			<div class="maintely-mode-schedule-toggle-control">
				<label class="maintely-mode-toggle" for="<?php echo esc_attr( $field_id ); ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $field_id ); ?>"
						name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[schedule_enabled]"
						value="1"
						<?php checked( true, $enabled ); ?>
					/>
					<span class="maintely-mode-toggle-track" aria-hidden="true">
						<span class="maintely-mode-toggle-thumb"></span>
					</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Toggle automatic scheduling', 'maintely-mode' ); ?></span>
				</label>
			</div>
		</div>
		<?php
		$this->render_schedule_status();
	}

	/**
	 * Render: Start Date & Time and End Date & Time fields.
	 *
	 * Rendered together as one field so both dates can sit side by side
	 * in a single "scheduled window" card. The inputs still submit as
	 * two independent values (schedule_start / schedule_end) exactly as
	 * before - sanitize()/get_field_schema() are untouched - only the
	 * presentation is merged. When Automatic Scheduling is off, the
	 * card is dimmed and the fields are taken out of tab order; the
	 * "disabled" HTML attribute is intentionally never used here so a
	 * save made while scheduling is off cannot wipe out stored dates.
	 *
	 * @return void
	 */
	public function render_field_schedule_window() {
		$enabled     = (bool) maintely_mode_get_option( 'schedule_enabled', false );
		$start_value = maintely_mode_get_option( 'schedule_start', '' );
		$end_value   = maintely_mode_get_option( 'schedule_end', '' );
		$inert_attrs = $enabled ? '' : ' tabindex="-1" aria-disabled="true"';
		?>
		<div class="maintely-mode-schedule-fields-card<?php echo esc_attr( $enabled ? '' : ' is-disabled' ); ?>">
			<div class="maintely-mode-schedule-field">
				<label class="maintely-mode-schedule-field-label" for="maintely_mode_schedule_start">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Start Date & Time', 'maintely-mode' ); ?>
				</label>
				<input
					type="datetime-local"
					id="maintely_mode_schedule_start"
					class="maintely-mode-schedule-input"
					name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[schedule_start]"
					value="<?php echo esc_attr( $start_value ); ?>"
					<?php echo $inert_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				/>
				<p class="description">
					<?php esc_html_e( 'Maintenance mode turns on automatically at this time.', 'maintely-mode' ); ?>
				</p>
			</div>
			<div class="maintely-mode-schedule-field">
				<label class="maintely-mode-schedule-field-label" for="maintely_mode_schedule_end">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'End Date & Time', 'maintely-mode' ); ?>
				</label>
				<input
					type="datetime-local"
					id="maintely_mode_schedule_end"
					class="maintely-mode-schedule-input"
					name="<?php echo esc_attr( MAINTELY_MODE_OPTION_KEY ); ?>[schedule_end]"
					value="<?php echo esc_attr( $end_value ); ?>"
					<?php echo $inert_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				/>
				<p class="description">
					<?php esc_html_e( 'Maintenance mode turns off automatically at this time.', 'maintely-mode' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Show the next scheduled WP-Cron run, if any, as a quick status hint.
	 *
	 * @return void
	 */
	private function render_schedule_status() {

		if ( ! class_exists( 'Maintely_Mode_Scheduler' ) ) {
			return;
		}

		$next_activate   = wp_next_scheduled( Maintely_Mode_Scheduler::ACTIVATE_HOOK );
		$next_deactivate = wp_next_scheduled( Maintely_Mode_Scheduler::DEACTIVATE_HOOK );

		if ( ! $next_activate && ! $next_deactivate ) {
			return;
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<p class="description maintely-mode-schedule-status-hint">
			<?php if ( $next_activate ) : ?>
				<?php
				printf(
					/* translators: %s: formatted local date/time */
					esc_html__( 'Will turn ON at %s.', 'maintely-mode' ),
					esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_activate ), $format ) )
				);
				?>
				<br />
			<?php endif; ?>
			<?php if ( $next_deactivate ) : ?>
				<?php
				printf(
					/* translators: %s: formatted local date/time */
					esc_html__( 'Will turn OFF at %s.', 'maintely-mode' ),
					esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_deactivate ), $format ) )
				);
				?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Build the settings section ID for a given tab.
	 *
	 * @param string $tab_slug Tab slug.
	 * @return string
	 */
	public function get_section_id( $tab_slug ) {
		return 'maintely_mode_' . $tab_slug . '_section';
	}

	/**
	 * Settings section callback.
	 *
	 * Outputs a hidden field recording which tab is being submitted, so
	 * sanitize() knows exactly which keys to validate and which to
	 * leave untouched. Rendered once at the top of each tab's section,
	 * before any fields later phases add to it - and, crucially,
	 * before do_settings_sections() opens the "<table class=form-table>"
	 * that wraps the tab's fields. The General tab uses that position
	 * to render the Maintenance Mode status card as a full-width block
	 * sitting above the table, rather than as a table row - table cell
	 * layout is what was constraining its width.
	 *
	 * @param array $args Section args, as passed by do_settings_sections().
	 * @return void
	 */
	public function render_section_marker( $args ) {
		$section_id = isset( $args['id'] ) ? $args['id'] : '';
		$tab_slug   = preg_replace( '/^maintely_mode_(.+)_section$/', '$1', $section_id );

		printf(
			'<input type="hidden" name="maintely_mode_active_tab" value="%s" />',
			esc_attr( $tab_slug )
		);

		if ( 'general' === $tab_slug ) {
			$this->render_maintenance_card();
		}

		if ( 'contact' === $tab_slug ) {
			$this->render_contact_section_intro();
		}

		if ( 'social' === $tab_slug ) {
			$this->render_social_section_intro();
		}

		if ( 'access' === $tab_slug ) {
			$this->render_access_section_intro();
		}

		if ( 'design' === $tab_slug ) {
			$this->render_design_section_intro();
		}

		if ( 'schedule' === $tab_slug ) {
			$this->render_schedule_section_intro();
		}
	}

	/**
	 * Section intro: heading/description card for the Contact tab.
	 *
	 * Mirrors the pattern already used by the General tab's "Content"
	 * and "Branding" section headers - purely presentational, rendered
	 * just before do_settings_sections() opens the Contact tab's
	 * <table class="form-table">, so the table that follows can be
	 * visually attached to it as one card.
	 *
	 * @return void
	 */
	public function render_contact_section_intro() {
		?>
		<div class="maintely-mode-contact-section-header">
			<h2 class="maintely-mode-contact-section-title"><?php esc_html_e( 'Contact Details', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-contact-section-description">
				<?php esc_html_e( 'How visitors can reach you while the site is in maintenance mode.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section intro: heading/description card for the Social Media tab.
	 *
	 * Mirrors render_contact_section_intro() - purely presentational,
	 * rendered just before do_settings_sections() opens the Social
	 * tab's <table class="form-table">, so the table that follows can
	 * be visually attached to it as one card.
	 *
	 * @return void
	 */
	public function render_social_section_intro() {
		?>
		<div class="maintely-mode-social-section-header">
			<h2 class="maintely-mode-social-section-title"><?php esc_html_e( 'Social Media', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-social-section-description">
				<?php esc_html_e( 'Link your social profiles so visitors can stay connected while the site is in maintenance mode.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section intro: heading/description card for the Access Control tab.
	 *
	 * Mirrors render_contact_section_intro() / render_social_section_intro() -
	 * purely presentational, rendered just before do_settings_sections()
	 * opens the Access tab's <table class="form-table">, so the table
	 * that follows can be visually attached to it as one card.
	 *
	 * @return void
	 */
	public function render_access_section_intro() {
		?>
		<div class="maintely-mode-access-section-header">
			<h2 class="maintely-mode-access-section-title"><?php esc_html_e( 'Access Control', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-access-section-description">
				<?php esc_html_e( 'Manage the secret link that lets you and your team preview or bypass maintenance mode.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section intro: heading/description card for the Design tab.
	 *
	 * Mirrors render_contact_section_intro() / render_social_section_intro() /
	 * render_access_section_intro() - purely presentational, rendered
	 * just before do_settings_sections() opens the Design tab's
	 * <table class="form-table">, so the table that follows can be
	 * visually attached to it as one card.
	 *
	 * @return void
	 */
	public function render_design_section_intro() {
		?>
		<div class="maintely-mode-design-section-header">
			<h2 class="maintely-mode-design-section-title"><?php esc_html_e( 'Design', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-design-section-description">
				<?php esc_html_e( 'Control how the maintenance page looks and feels for your visitors.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section intro: heading/description card for the Schedule tab.
	 *
	 * Mirrors render_contact_section_intro() / render_social_section_intro() /
	 * render_access_section_intro() / render_design_section_intro() -
	 * purely presentational, rendered just before do_settings_sections()
	 * opens the Schedule tab's <table class="form-table">, so the table
	 * that follows can be visually attached to it as one card.
	 *
	 * @return void
	 */
	public function render_schedule_section_intro() {
		?>
		<div class="maintely-mode-schedule-section-header">
			<h2 class="maintely-mode-schedule-section-title"><?php esc_html_e( 'Schedule', 'maintely-mode' ); ?></h2>
			<p class="maintely-mode-schedule-section-description">
				<?php esc_html_e( 'Automatically turn maintenance mode on and off at times you choose, instead of switching it manually.', 'maintely-mode' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * The full field schema: tab => { option key => sanitize type }.
	 *
	 * The single source of truth for which keys belong to which tab and
	 * how each one must be sanitized. Later phases render the actual
	 * <input> markup for these keys via add_settings_field() but do not
	 * need to duplicate this validation logic.
	 *
	 * @return array
	 */
	public function get_field_schema() {
		return array(
			'general'  => array(
				'maintenance_enabled' => 'checkbox',
				'maintenance_title'   => 'text',
				'maintenance_message' => 'textarea',
				'logo_id'             => 'int',
				'show_site_name'      => 'checkbox',
			),
			'contact'  => array(
				'contact_email'   => 'email',
				'contact_phone'   => 'phone',
				'whatsapp_number' => 'whatsapp_digits',
				'address'         => 'textarea',
			),
			'social'   => array(
				'social_facebook'  => 'url',
				'social_instagram' => 'url',
				'social_linkedin'  => 'url',
				'social_x'         => 'url',
				'social_custom'    => 'social_repeater',
			),
			'access'   => array(
				'secret_access_token' => 'token',
			),
			'design'   => array(
				'theme_mode'       => 'theme_mode',
				'enable_particles' => 'checkbox',
			),
			'schedule' => array(
				'schedule_enabled' => 'checkbox',
				'schedule_start'   => 'datetime',
				'schedule_end'     => 'datetime',
			),
		);
	}

	/**
	 * Sanitize callback for the single plugin option.
	 *
	 * Starts from the previously stored (default-filled) options, then
	 * overwrites only the keys belonging to the submitted tab. Every
	 * other tab's values pass through untouched, so submitting the
	 * "Contact" form, for example, can never blank out "Social" values.
	 *
	 * IMPORTANT: because this is registered via register_setting(), WP
	 * runs it on *every* update_option( MAINTELY_MODE_OPTION_KEY, ... )
	 * call anywhere in the plugin - not only when the person submits
	 * one of the tab forms on the Settings page via options.php. The
	 * `maintely_mode_active_tab` marker is only ever present in $_POST
	 * for that specific form submission, so it doubles as the signal
	 * for "this is a real tab submission that needs merging against
	 * the previous value". When it's absent, this is a direct,
	 * programmatic write from elsewhere in the plugin (e.g. rotating
	 * the secret access token) that already built a complete, trusted
	 * options array - it must be saved as-is. Falling back to
	 * $existing in that case would silently discard the caller's
	 * change and re-save the old value, making the write a no-op.
	 *
	 * @param mixed $input Raw value passed to update_option()/from $_POST.
	 * @return array
	 */
	public function sanitize( $input ) {

		$existing = maintely_mode_get_options();
		$output   = $existing;
		$schema   = $this->get_field_schema();

		$submitted_tab = isset( $_POST['maintely_mode_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['maintely_mode_active_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! array_key_exists( $submitted_tab, $schema ) ) {
			// Not a submission of one of our tab forms - pass a
			// programmatic update straight through instead of
			// clobbering it with the previously stored value.
			return is_array( $input ) ? $input : $output;
		}

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		foreach ( $schema[ $submitted_tab ] as $key => $type ) {
			$raw_value      = array_key_exists( $key, $input ) ? $input[ $key ] : null;
			$output[ $key ] = $this->sanitize_field( $type, $raw_value, $existing[ $key ] );
		}

		if ( $output !== $existing ) {
			add_settings_error(
				MAINTELY_MODE_OPTION_KEY,
				'maintely_mode_settings_saved',
				__( 'Settings saved.', 'maintely-mode' ),
				'success'
			);
		}

		return $output;
	}

	/**
	 * Sanitize a single value according to its declared field type.
	 *
	 * @param string $type    One of the types used in get_field_schema().
	 * @param mixed  $raw     Raw submitted value, or null if not present.
	 * @param mixed  $current The currently stored value, used as a safe fallback.
	 * @return mixed
	 */
	private function sanitize_field( $type, $raw, $current ) {

		switch ( $type ) {

			case 'checkbox':
				return ! empty( $raw );

			case 'text':
				return is_string( $raw ) ? sanitize_text_field( $raw ) : '';

			case 'textarea':
				return is_string( $raw ) ? sanitize_textarea_field( $raw ) : '';

			case 'int':
				return is_scalar( $raw ) ? absint( $raw ) : 0;

			case 'email':
				$email = is_string( $raw ) ? sanitize_email( $raw ) : '';
				return is_email( $email ) ? $email : '';

			case 'phone':
				return is_string( $raw ) ? preg_replace( '/[^0-9+\-\s()]/', '', $raw ) : '';

			case 'whatsapp_digits':
				return is_string( $raw ) ? preg_replace( '/[^0-9]/', '', $raw ) : '';

			case 'url':
				return ( is_string( $raw ) && '' !== trim( $raw ) ) ? esc_url_raw( trim( $raw ) ) : '';

			case 'token':
				return is_string( $raw ) && '' !== trim( $raw ) ? sanitize_text_field( $raw ) : $current;

			case 'theme_mode':
				$value = is_string( $raw ) ? sanitize_key( $raw ) : '';
				return in_array( $value, array( 'light', 'dark' ), true ) ? $value : 'light';

			case 'datetime':
				if ( is_string( $raw ) && preg_match( '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2})?$/', trim( $raw ) ) ) {
					return sanitize_text_field( trim( $raw ) );
				}
				return '';

			case 'social_repeater':
				return $this->sanitize_social_repeater( $raw );

			default:
				return $current;
		}
	}

	/**
	 * Sanitize the repeatable "custom social links" rows.
	 *
	 * Expects an array of { label, url } rows. Rows missing both a
	 * label and a URL are dropped entirely.
	 *
	 * @param mixed $raw Raw submitted value.
	 * @return array
	 */
	private function sanitize_social_repeater( $raw ) {

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';

			if ( '' === $label && '' === $url ) {
				continue;
			}

			$clean[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $clean;
	}
}
