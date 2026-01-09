<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$block_args = wp_parse_args( $block_args ?? [], [
  'posts'    => [],
  'layout'   => 'stacked',
  'title'    => 'Suggested Reading',
  'position' => 'default',
]);

$posts    = $block_args['posts'];
$layout   = $block_args['layout'];
$title    = $block_args['title'];
$position = $block_args['position'];

if ( empty( $posts ) ) return;
?>

<section class="suggested-reading suggested-reading--<?php echo esc_attr( $layout ); ?> suggested-reading--<?php echo esc_attr( $position ); ?><?php echo 'top' === $position ? ' suggested-reading-sidebar is-hidden' : ''; ?>" <?php echo 'top' === $position ? 'data-sr-reveal="top"' : ''; ?> aria-label="<?php echo esc_attr( $title ); ?>">

  <?php if ( ! empty( $title ) ) : ?>
    <h2 class="sr-sidebar-heading"><?php echo esc_html( $title ); ?></h2>
  <?php endif; ?>

  <ul class="sr-card-list">
    <?php foreach ( $posts as $post ) : setup_postdata( $post ); ?>
      <li class="sr-card">
        <div class="sr-card-inner">
          <div class="sr-card-thumb">
            <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="sr-card-thumb-link">
              <?php
              if ( has_post_thumbnail( $post->ID ) ) {
                echo get_the_post_thumbnail( $post->ID, 'thumbnail', [ 'class' => 'sr-card-image' ] );
              } else {
                echo '<div class="sr-thumb-placeholder"></div>';
              }
              ?>
            </a>
          </div>

          <div class="sr-card-meta">
            <?php
            $category = get_field( 'override_lead_category', $post->ID );
            if ( ! $category && function_exists( 'get_the_category' ) ) {
              $categories = get_the_category( $post->ID );
              $category = $categories[0] ?? null;
            }

            $lead_category = null;
            if ( function_exists( 'touchpointcrm_get_lead_category_id' ) ) {
              $lead_id = touchpointcrm_get_lead_category_id( $post->ID );
              if ( $lead_id ) {
                $lead_category = get_term( $lead_id, 'category' );
                if ( $lead_category && is_wp_error( $lead_category ) ) {
                  $lead_category = null;
                }
              }
            }

            $content = get_post_field( 'post_content', $post->ID );
            $word_count = str_word_count( wp_strip_all_tags( $content ) );
            $reading_time = max( 1, (int) ceil( $word_count / 200 ) );
            ?>

            <div class="sr-card-meta-line">
              <?php if ( $lead_category && is_object( $lead_category ) ) : ?>
                <span class="sr-card-meta-item sr-card-category sr-card-category--lead"><?php echo esc_html( $lead_category->name ); ?></span>
              <?php endif; ?>
              <?php if ( $lead_category && is_object( $lead_category ) && $category && is_object( $category ) ) : ?>
                <span class="sr-card-meta-sep">|</span>
              <?php endif; ?>
              <?php if ( $category && is_object( $category ) ) : ?>
                <span class="sr-card-meta-item sr-card-category sr-card-category--primary"><?php echo esc_html( $category->name ); ?></span>
              <?php endif; ?>
            </div>

            <div class="sr-card-title">
              <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="sr-card-title-link">
                <?php echo esc_html( get_the_title( $post ) ); ?>
              </a>
            </div>

            <div class="sr-card-reading-time"><?php echo esc_html( $reading_time ); ?> min read</div>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
    <?php wp_reset_postdata(); ?>
  </ul>
</section>
