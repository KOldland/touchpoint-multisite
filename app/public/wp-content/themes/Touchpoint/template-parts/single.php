 <main id="content" <?php post_class('site-main'); ?>>


  <?php if (apply_filters('touchpoint_crm_page_title', true)) : ?>
    <div class="kh-post-header">
      <?php
        // Pull primary category
        $cat_id = function_exists( 'touchpointcrm_get_lead_category_id' ) ? touchpointcrm_get_lead_category_id( get_the_ID() ) : 0;
        if ( $cat_id ) {
          $term = get_term( $cat_id, 'category' );
          if ( $term && ! is_wp_error( $term ) ) {
            echo '<a class="kh-post-category" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
          }
        }
      ?>
      <?php the_title('<h1 class="kh-post-title">', '</h1>'); ?>
      
      <?php if (has_excerpt()) : ?>
        <div class="excerpt-container">
          <?php
            $excerpt_full = get_the_excerpt();
            $excerpt_short = wp_trim_words( $excerpt_full, 30, '…' );
          ?>
          <span class="excerpt-text" data-full="<?php echo esc_attr( $excerpt_full ); ?>" data-short="<?php echo esc_attr( $excerpt_short ); ?>">
            <?php echo esc_html( $excerpt_short ); ?>
          </span>
          <button class="excerpt-toggle" type="button">More</button>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="page-content">
    <?php echo do_shortcode('[abstract_block]'); ?>
    <div class="sr-abstract-end-anchor" aria-hidden="true"></div>
    <?php the_content(); ?>
    <?php wp_link_pages(); ?>

    <?php if (has_tag()) : ?>
      <div class="post-tags">
        <?php the_tags('<span class="tag-links">' . esc_html__('Tagged: ', 'touchpoint-crm'), ', ', '</span>'); ?>
      </div>
    <?php endif; ?>
  </div>

  <?php
    // Comments template
    comments_template();
  ?>

  <?php
    //  Multiple Authors Output
    $authors = function_exists('kh_get_post_authors') ? kh_get_post_authors( get_the_ID() ) : get_field('authors');
    if (!empty($authors) && is_array($authors)) :
      echo '<section class="multi-author-block" aria-label="Contributing Authors">';
      foreach ($authors as $author_post) {
        echo '<div class="author-entry">';
        echo kh_render_author_block($author_post->ID); // Render each fancy author block
        echo '</div>';
      }
      echo '</section>';
    endif;
  ?>

</main>
