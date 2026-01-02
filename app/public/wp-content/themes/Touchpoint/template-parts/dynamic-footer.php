<?php
/**
 * The template for displaying footer.
 *
 * @package Touchpoint CRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$site_name = get_bloginfo( 'name' );
$tagline   = get_bloginfo( 'description', 'display' );
$footer_nav_menu = wp_nav_menu( [
	'theme_location' => 'menu-2',
	'fallback_cb' => false,
	'container' => false,
	'echo' => false,
] );
?>
<footer id="site-footer" class="site-footer">
	<div class="footer-inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<div class="site-logo">
					<?php the_custom_logo(); ?>
				</div>
			<?php endif; ?>

			<?php if ( $site_name ) : ?>
				<div class="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr__( 'Home', 'touchpoint-crm' ); ?>" rel="home">
						<?php echo esc_html( $site_name ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $tagline ) : ?>
				<p class="site-description">
					<?php echo esc_html( $tagline ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $footer_nav_menu ) : ?>
			<nav class="site-navigation" aria-label="<?php echo esc_attr__( 'Footer menu', 'touchpoint-crm' ); ?>">
				<?php echo $footer_nav_menu; ?>
			</nav>
		<?php endif; ?>

		<?php if ( '' !== get_option( 'footer_copyright_text' ) ) : ?>
			<div class="copyright">
				<p><?php echo wp_kses_post( get_option( 'footer_copyright_text' ) ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</footer>
