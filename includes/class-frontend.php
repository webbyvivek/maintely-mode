<?php
/**
 * Frontend maintenance page rendering.
 *
 * @package Maintely_Mode
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maintely_Mode_Frontend.
 *
 * Builds the data the maintenance page template needs from the stored
 * settings, and includes that template. This class only knows how to
 * render the page - deciding *when* a visitor should see it (checking
 * maintenance_enabled, admin bypass, the secret-URL cookie, and
 * preventing redirect loops) is the maintenance engine's job
 * (Maintely_Mode_Maintenance, Phase 17), which will call render() once
 * built. No hooks are registered here yet for that reason.
 */
class Maintely_Mode_Frontend {

	/**
	 * Handle used to register/enqueue the maintenance page's stylesheet.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'maintely-mode-maintenance';

	/**
	 * Render the full maintenance page document and stop execution.
	 *
	 * @return void
	 */
	public function render() {

		$data     = $this->get_template_data();
		$template = MAINTELY_MODE_TEMPLATES_DIR . 'maintenance.php';

		if ( ! file_exists( $template ) ) {
			return;
		}

		$this->enqueue_style( $data );

		include $template;
	}

	/**
	 * Register and enqueue the maintenance page's stylesheet.
	 *
	 * This template is a fully standalone document that never calls
	 * wp_head() (see the class docblock), so wp_enqueue_scripts is
	 * used only to register/enqueue the handle; the template itself
	 * prints it in <head> via wp_print_styles() at the exact point the
	 * stylesheet belongs, since the normal wp_head() output hook is
	 * never fired on this page.
	 *
	 * @param array $data Template data, as built by get_template_data().
	 * @return void
	 */
	private function enqueue_style( $data ) {

		wp_register_style(
			self::STYLE_HANDLE,
			$data['css_url'],
			array(),
			$data['css_version']
		);

		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/**
	 * Gather and lightly prepare every value the template needs.
	 *
	 * Values are prepared here (URLs resolved, empty rows filtered) but
	 * left un-escaped - the template is responsible for escaping at the
	 * point of output, per WordPress best practice.
	 *
	 * @return array
	 */
	private function get_template_data() {

		$options = maintely_mode_get_options();

		$logo_url = ! empty( $options['logo_id'] ) ? wp_get_attachment_image_url( (int) $options['logo_id'], 'medium' ) : '';

		$whatsapp_url = '';
		if ( ! empty( $options['whatsapp_number'] ) ) {
			$whatsapp_url = 'https://wa.me/' . rawurlencode( $options['whatsapp_number'] );
		}

		return array(
			'site_name'            => get_bloginfo( 'name' ),
			'show_site_name'       => ! empty( $options['show_site_name'] ),
			'logo_url'             => $logo_url,
			'favicon_url'          => MAINTELY_MODE_ASSETS_URL . 'images/maintely-site-icon.png',
			'favicon_version'      => maintely_mode_asset_version( 'images/maintely-site-icon.png' ),
			'brand_logo_url'       => MAINTELY_MODE_ASSETS_URL . 'images/brand.png',
			'brand_logo_version'   => maintely_mode_asset_version( 'images/brand.png' ),
			'title'                => $options['maintenance_title'],
			'message'              => $options['maintenance_message'],
			'contact_email'        => $options['contact_email'],
			'contact_phone'        => $options['contact_phone'],
			'address'              => $options['address'],
			'whatsapp_url'         => $whatsapp_url,
			'social_links'         => $this->get_social_links( $options ),
			'theme_mode'           => ( 'dark' === $options['theme_mode'] ) ? 'dark' : 'light',
			'enable_particles'     => ! empty( $options['enable_particles'] ),
			'css_url'              => MAINTELY_MODE_ASSETS_URL . 'css/maintenance.css',
			'css_version'          => maintely_mode_asset_version( 'css/maintenance.css' ),
			'particles_js_url'     => MAINTELY_MODE_ASSETS_URL . 'js/maintenance-particles.js',
			'particles_js_version' => maintely_mode_asset_version( 'js/maintenance-particles.js' ),
		);
	}

	/**
	 * Build the flat list of social links to display: the five fixed
	 * networks (only if a URL was set) followed by the unlimited custom
	 * links, skipping any row missing a URL.
	 *
	 * @param array $options Full plugin options array.
	 * @return array[] Each item: { slug, label, url }.
	 */
	private function get_social_links( $options ) {

		$fixed = array(
			'facebook'  => array(
				'key'   => 'social_facebook',
				'label' => __( 'Facebook', 'maintely-mode' ),
			),
			'instagram' => array(
				'key'   => 'social_instagram',
				'label' => __( 'Instagram', 'maintely-mode' ),
			),
			'linkedin'  => array(
				'key'   => 'social_linkedin',
				'label' => __( 'LinkedIn', 'maintely-mode' ),
			),
			'x'         => array(
				'key'   => 'social_x',
				'label' => __( 'X', 'maintely-mode' ),
			),
			'youtube'   => array(
				'key'   => 'social_youtube',
				'label' => __( 'YouTube', 'maintely-mode' ),
			),
		);

		$links = array();

		foreach ( $fixed as $slug => $info ) {
			if ( ! empty( $options[ $info['key'] ] ) ) {
				$links[] = array(
					'slug'  => $slug,
					'label' => $info['label'],
					'url'   => $options[ $info['key'] ],
				);
			}
		}

		if ( ! empty( $options['social_custom'] ) && is_array( $options['social_custom'] ) ) {
			foreach ( $options['social_custom'] as $row ) {
				if ( empty( $row['url'] ) ) {
					continue;
				}

				$links[] = array(
					'slug'  => 'custom',
					'label' => ! empty( $row['label'] ) ? $row['label'] : __( 'Link', 'maintely-mode' ),
					'url'   => $row['url'],
				);
			}
		}

		return $links;
	}

}
