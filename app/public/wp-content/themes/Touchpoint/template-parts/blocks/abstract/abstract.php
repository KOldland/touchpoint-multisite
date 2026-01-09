<div class="abstract-block">
  <h2><u>Abstract</u></h2>

  <?php
    
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
    $value = get_field($field);
    if( $value ) : 
  ?>
    <h4><?php echo esc_html($label); ?></h4>
    <p><?php echo wp_kses_post($value); ?></p>
  <?php 
    endif;
  endforeach;
  ?>

  <?php if( have_rows('key_points') ): ?>
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
