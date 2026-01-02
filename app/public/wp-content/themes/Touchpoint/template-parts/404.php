<?php
/**
 * The template for displaying 404 pages (not found).
 *
 * @package Touchpoint CRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<main id="content" class="site-main">

	<?php if ( apply_filters( 'touchpoint_crm_page_title', true ) ) : ?>
		<div class="page-header">
			<h1 class="entry-title"><?php echo esc_html__( 'Oops! The page can&rsquo;t be found.', 'touchpoint-crm' ); ?></h1>
		</div>
	<?php endif; ?>

	<div class="page-content">
		<p><?php echo esc_html__( 'It looks like nothing was found at this location. Perhaps try searching or browsing our latest posts below.', 'touchpoint-crm' ); ?></p>
		
		<!-- Search Form -->
		<div class="search-form">
			<?php get_search_form(); ?>
		</div>

		<!-- Suggested Posts -->
		<div class="suggested-posts">
			<h2><?php esc_html_e( 'Suggested Posts', 'touchpoint-crm' ); ?></h2>
			<ul>
				<?php
				$args = array(
					'posts_per_page' => 5,
					'orderby' => 'rand',
				);
				$random_posts = new WP_Query( $args );
				if ( $random_posts->have_posts() ) :
					while ( $random_posts->have_posts() ) : $random_posts->the_post();
						?>
						<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					echo '<li>' . esc_html__( 'No suggested posts available at the moment.', 'touchpoint-crm' ) . '</li>';
				endif;
				?>
			</ul>
		</div>

		<!-- Back to Home Button -->
		<div class="back-home">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button"><?php esc_html_e( 'Go Back to Home', 'touchpoint-crm' ); ?></a>
		</div>
	</div>

</main>

