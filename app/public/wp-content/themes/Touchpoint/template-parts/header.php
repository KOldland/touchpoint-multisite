<?php
/**
 * The template for displaying the header.
 *
 * @package TouchpointCRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$site_name = get_bloginfo( 'name' );
$tagline   = get_bloginfo( 'description', 'display' );
$header_nav_menu = wp_nav_menu( [
	'theme_location' => 'menu-1',  // Ensure your menu is set as 'menu-1' in WordPress
	'fallback_cb' => false,
	'container' => false,
	'echo' => false,
] );
?>

<header id="site-header" class="site-header">

	<div class="site-branding">
		<?php if ( has_custom_logo() ) : ?>
			<div class="site-logo">
				<?php the_custom_logo(); ?>
			</div>
		<?php elseif ( $site_name ) : ?>
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

	<?php if ( $header_nav_menu ) : ?>
		<nav class="site-navigation" aria-label="<?php echo esc_attr__( 'Main menu', 'touchpoint-crm' ); ?>">
			<?php
			// Display the header navigation menu
			echo $header_nav_menu; 
			?>
		</nav>
	<?php endif; ?>

</header>
