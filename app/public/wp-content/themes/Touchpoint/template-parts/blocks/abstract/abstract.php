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
  // Display linked categories inline
  $categories = get_the_category();
  if( !empty($categories) ) : ?>
    <h4>Key Themes</h4>
    <p class="inline-keywords">
      <?php
      $output = array();
      foreach( $categories as $category ) {
        if( strtolower($category->name) !== 'uncategorized' ) {
          $output[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_category_link( $category->term_id ) ),
            esc_html( $category->name )
          );
        }
      }
      echo implode(', ', $output) . '.';
      ?>
    </p>
  <?php endif; ?>
</div>
