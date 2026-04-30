<div class="abstract-block">
  <h2><u>Abstract</u></h2>

  <?php

    $block_data = get_query_var('touchpoint_abstract_data');
    if ( ! is_array( $block_data ) ) {
      $block_data = array();
    }

    // Get the correct post ID manually in case we're in a shortcode
    $post_id = get_the_ID();
    if ( ! $post_id || ! is_numeric($post_id) ) {
      global $post;
      $post_id = isset($post->ID) ? $post->ID : null;
    }
  // Key-value map for fields and their headings
  $abstract_fields = [
    'overview'    => 'Overview',
    'context'     => 'Context',
    'application' => 'Application'
  ];

  // Loop through each field and render if content exists
  foreach( $abstract_fields as $field => $label ) :
    $value = isset( $block_data[ $field ] ) ? $block_data[ $field ] : '';
    if ( '' === (string) $value && function_exists( 'get_field' ) ) {
      $value = get_field($field);
    }
    if ( '' === (string) $value && $post_id ) {
      $value = get_post_meta( $post_id, $field, true );
    }
    if( $value ) : 
  ?>
    <h4><?php echo esc_html($label); ?></h4>
    <p><?php echo wp_kses_post($value); ?></p>
  <?php 
    endif;
  endforeach;

  $key_points = isset( $block_data['key_points'] ) && is_array( $block_data['key_points'] ) ? $block_data['key_points'] : array();
  if ( empty( $key_points ) && $post_id ) {
    $count = absint( get_post_meta( $post_id, 'key_points', true ) );
    for ( $i = 0; $i < $count; $i++ ) {
      $bullet = get_post_meta( $post_id, "key_points_{$i}_bullet", true );
      if ( '' !== (string) $bullet ) {
        $key_points[] = array( 'bullet' => $bullet );
      }
    }
  }
  ?>

  <?php if ( ! empty( $key_points ) ) : ?>
    <h4>Observations</h4>
    <ul>
      <?php foreach ( $key_points as $row ) :
        $bullet = isset( $row['bullet'] ) ? $row['bullet'] : '';
        if( $bullet ): ?>
          <li><?php echo esc_html($bullet); ?></li>
        <?php endif;
      endforeach; ?>
    </ul>
  <?php elseif ( function_exists( 'have_rows' ) && have_rows('key_points') ): ?>
    <h4>Observations</h4>
    <ul>
      <?php while( have_rows('key_points') ): the_row();
        $bullet = get_sub_field('bullet');
        if( $bullet ): ?>
          <li><?php echo esc_html($bullet); ?></li>
        <?php endif;
      endwhile; ?>
    </ul>
  <?php endif; ?>

  <?php
  // Display linked tags inline
  $tags = get_the_tags();
  if( !empty($tags) ) : ?>
    <h4>Key Themes</h4>
    <p class="inline-keywords">
      <?php
      $output = array();
      foreach( $tags as $tag ) {
          $output[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_tag_link( $tag->term_id ) ),
            esc_html( $tag->name )
          );
      }
      echo implode(', ', $output) . '.';
      ?>
    </p>
  <?php endif; ?>
</div>
<div class="sr-abstract-end-anchor" aria-hidden="true"></div>
