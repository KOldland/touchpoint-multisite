<?php
/**
 * The template for displaying archive pages.
 *
 * @package Touchpoint CRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<main id="content" class="site-main">

	<?php if ( touchpoint_apply_hello_filter( 'hello_elementor_page_title', true ) ) : ?>
		<div class="page-header">
			<h1 class="entry-title"><?php the_archive_title(); ?></h1>
			<p class="archive-description"><?php the_archive_description(); ?></p>
		</div>
	<?php endif; ?>

	<div class="page-content">
		<?php
		// Start the loop to display posts
		if ( have_posts() ) :
			while ( have_posts() ) : the_post();
				$post_link = get_permalink();
		?>
			<article class="post">
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php echo esc_url( $post_link ); ?>">
						<?php the_post_thumbnail( 'large' ); ?>
					</a>
				<?php endif; ?>
				
				<h2 class="entry-title">
					<a href="<?php echo esc_url( $post_link ); ?>">
						<?php the_title(); ?>
					</a>
				</h2>

				<p class="post-excerpt">
					<?php the_excerpt(); ?>
				</p>
			</article>
		<?php endwhile; ?>

		<?php else : ?>
			<p><?php esc_html_e( 'No posts found.', 'touchpoint' ); ?></p>
		<?php endif; ?>
	</div>

	<?php
	// Pagination
	global $wp_query;
	if ( $wp_query->max_num_pages > 1 ) :
		$prev_arrow = is_rtl() ? '&rarr;' : '&larr;';
		$next_arrow = is_rtl() ? '&larr;' : '&rarr;';
		?>
		<nav class="pagination">
			<div class="nav-previous"><?php
				/* translators: %s: HTML entity for arrow character. */
				previous_posts_link( sprintf( esc_html__( '%s Previous', 'touchpoint' ), sprintf( '<span class="meta-nav">%s</span>', $prev_arrow ) ) );
			?></div>
			<div class="nav-next"><?php
				/* translators: %s: HTML entity for arrow character. */
				next_posts_link( sprintf( esc_html__( 'Next %s', 'touchpoint' ), sprintf( '<span class="meta-nav">%s</span>', $next_arrow ) ) );
			?></div>
		</nav>
	<?php endif; ?>

</main>

<?php
// End of archive.php
