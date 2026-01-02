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

<section class="suggested-reading suggested-reading--<?php echo esc_attr( $layout ); ?> suggested-reading--<?php echo esc_attr( $position ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">

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

            if ( $category && is_object( $category ) ) {
              echo '<div class="sr-card-category">' . esc_html( $category->name ) . '</div>';
            }
            ?>

            <h3 class="sr-card-title">
              <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" class="sr-card-title-link">
                <?php echo esc_html( get_the_title( $post ) ); ?>
              </a>
            </h3>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
    <?php wp_reset_postdata(); ?>
  </ul>
</section>
