 <main id="content" <?php post_class('site-main'); ?>>


  <?php if (apply_filters('touchpoint_crm_page_title', true)) : ?>
    <div class="page-header">
      <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
      
      <?php if (has_excerpt()) : ?>
        <div class="excerpt-container">
          <span class="excerpt-text"><?php echo get_the_excerpt(); ?></span>
          <button class="excerpt-toggle">More</button>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php
    // Pull primary category
    $cat_id = get_field('primary_category');
    if ($cat_id) {
      $term = get_term($cat_id, 'category');
      if (!is_wp_error($term)) {
        echo '<div class="primary-category"><span class="post-meta main-category-label">' . esc_html($term->name) . '</span></div>';
      }
    }
  ?>

  <div class="page-content">
    <?php echo do_shortcode('[abstract_block]'); ?>
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
    $authors = get_field('authors'); // Relationship field: returns array of post objects
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
