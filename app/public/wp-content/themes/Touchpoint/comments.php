<?php
/**
 * Comments template.
 *
 * @package Touchpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! post_type_supports( get_post_type(), 'comments' ) || ( ! have_comments() && ! comments_open() ) ) {
	return;
}

if ( comments_open() && get_option( 'thread_comments' ) ) {
	wp_enqueue_script( 'comment-reply' );
}
?>

<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="title-comments">
			<?php
			$comments_number = get_comments_number();
			echo esc_html(
				sprintf(
					_n( '%s Response', '%s Responses', $comments_number, 'touchpoint' ),
					number_format_i18n( $comments_number )
				)
			);
			?>
		</h2>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list">
			<?php
			wp_list_comments( [
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 42,
			] );
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php
	comment_form( [
		'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
		'title_reply_after'  => '</h2>',
	] );
	?>
</section>
