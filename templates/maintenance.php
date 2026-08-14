<?php
/**
 * Frontend maintenance page template.
 *
 * Expects $data (array) to already be in scope - populated by
 * Maintely_Mode_Frontend::render(). This is a fully standalone HTML
 * document: it intentionally does not load the active theme (no
 * get_header()/get_footer()), so the maintenance experience stays
 * fast, on-brand, and unaffected by whatever the theme is doing.
 *
 * @package Maintely_Mode
 * @var array $data
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$maintely_mode_theme_class    = ( 'dark' === $data['theme_mode'] ) ? 'maintely-mode-theme-dark' : 'maintely-mode-theme-light';
$maintely_mode_document_title = $data['title'] ? $data['title'] . ' - ' . $data['site_name'] : $data['site_name'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr( $maintely_mode_theme_class ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $maintely_mode_document_title ); ?></title>
	<link rel="icon" href="<?php echo esc_url( add_query_arg( 'ver', $data['favicon_version'], $data['favicon_url'] ) ); ?>" />
	<?php wp_print_styles( array( Maintely_Mode_Frontend::STYLE_HANDLE ) ); ?>
</head>
<body class="maintely-mode-body">

	<?php if ( $data['enable_particles'] ) : ?>
		<canvas id="maintely-mode-particles" class="maintely-mode-particles" aria-hidden="true"></canvas>
	<?php endif; ?>

	<main class="maintely-mode-card" role="main">

		<?php if ( $data['logo_url'] ) : ?>
			<div class="maintely-mode-logo">
				<img src="<?php echo esc_url( $data['logo_url'] ); ?>" alt="<?php echo esc_attr( $data['site_name'] ); ?>" />
			</div>
		<?php endif; ?>

		<?php if ( $data['show_site_name'] && $data['site_name'] ) : ?>
			<p class="maintely-mode-site-name"><?php echo esc_html( $data['site_name'] ); ?></p>
		<?php endif; ?>

		<?php if ( $data['title'] ) : ?>
			<h1 class="maintely-mode-title"><?php echo esc_html( $data['title'] ); ?></h1>
		<?php endif; ?>

		<?php if ( $data['message'] ) : ?>
			<p class="maintely-mode-message">
				<?php echo esc_html( $data['message'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $data['contact_email'] || $data['contact_phone'] || $data['whatsapp_url'] || $data['address'] ) : ?>
			<div class="maintely-mode-contact-wrap">
				<?php if ( $data['address'] ) : ?>
					<p class="maintely-mode-address"><?php echo nl2br( esc_html( $data['address'] ), false ); ?></p>
				<?php endif; ?>
				<?php if ( $data['contact_email'] || $data['contact_phone'] || $data['whatsapp_url'] ) : ?>
					<div class="maintely-mode-contact">
						<?php if ( $data['contact_email'] ) : ?>
							<a class="maintely-mode-contact-link" href="<?php echo esc_url( 'mailto:' . $data['contact_email'] ); ?>">
								<?php echo esc_html( $data['contact_email'] ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $data['contact_phone'] ) : ?>
							<a class="maintely-mode-contact-link" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $data['contact_phone'] ) ); ?>">
								<?php echo esc_html( $data['contact_phone'] ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $data['whatsapp_url'] ) : ?>
							<a class="maintely-mode-contact-link maintely-mode-whatsapp" href="<?php echo esc_url( $data['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'WhatsApp', 'maintely-mode' ); ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $data['social_links'] ) ) : ?>
			<div class="maintely-mode-social">
				<?php foreach ( $data['social_links'] as $link ) : ?>
					<a
						class="maintely-mode-social-link maintely-mode-social-<?php echo esc_attr( $link['slug'] ); ?>"
						href="<?php echo esc_url( $link['url'] ); ?>"
						target="_blank"
						rel="noopener noreferrer"
					>
						<?php echo esc_html( $link['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	</main>

	<?php if ( $data['brand_logo_url'] ) : ?>
		<div class="maintely-mode-powered-by">
			<span class="maintely-mode-powered-by-label"><?php esc_html_e( 'Powered by', 'maintely-mode' ); ?></span>
			<img
				class="maintely-mode-powered-by-logo"
				src="<?php echo esc_url( add_query_arg( 'ver', $data['brand_logo_version'], $data['brand_logo_url'] ) ); ?>"
				alt="VirtualCode"
				width="936"
				height="194"
				loading="lazy"
			/>
		</div>
	<?php endif; ?>

	<?php if ( $data['enable_particles'] ) : ?>
		<script
			src="<?php echo esc_url( add_query_arg( 'ver', $data['particles_js_version'], $data['particles_js_url'] ) ); ?>"
			defer
		></script>
	<?php endif; ?>

</body>
</html>
