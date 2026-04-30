  <div class="kss-footnotes">
    <h3><u>Footnotes</u></h3>
    
    <?php
      $block_data = get_query_var('touchpoint_footnotes_data');
      $footnotes = is_array( $block_data ) && isset( $block_data['footnotes'] ) && is_array( $block_data['footnotes'] )
        ? $block_data['footnotes']
        : array();

      if ( empty( $footnotes ) && function_exists( 'get_field' ) ) {
        $acf_footnotes = get_field('footnotes');
        if ( is_array( $acf_footnotes ) ) {
          $footnotes = $acf_footnotes;
        }
      }

      if ( empty( $footnotes ) || ! is_array( $footnotes ) ) {
        $post_id = get_the_ID();
        if ( $post_id && function_exists( 'touchpoint_extract_repeater_rows_from_meta' ) ) {
          $footnotes = touchpoint_extract_repeater_rows_from_meta(
            $post_id,
            'footnotes',
            array( 'reference_text', 'reference_link', 'publication_date', 'lead_author', 'additional_authors' )
          );
        }
      }

      if ($footnotes) {
        echo '<ol>';
        foreach ($footnotes as $item) {
          $text = $item['reference_text'];
          $link = $item['reference_link'];
          $publication_date = $item['publication_date'] ?? '';
          $lead_author = $item['lead_author'] ?? '';
          $additional_authors = $item['additional_authors'] ?? '';
          
          if ($text) {
            echo '<li>';
            if ($link) {
              echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($text) . '</a>';
            } else {
              echo esc_html($text);
            }
            if ($lead_author || $additional_authors || $publication_date) {
              echo '<br /><span class="footnotes-meta">';
              $meta_parts = array();
              if ($lead_author) {
                $meta_parts[] = esc_html($lead_author);
              }
              if ($additional_authors) {
                $meta_parts[] = esc_html($additional_authors);
              }
              if ($publication_date) {
                $meta_parts[] = esc_html($publication_date);
              }
              echo implode(' · ', $meta_parts);
              echo '</span>';
            }
            echo '</li>';
          }
        }
        echo '</ol>';
        echo '<br><br>';
        echo '<hr class="footnotes-divider" />';
    
      } else {
        echo '<p><em>No footnotes found. Must be a flawless article.</em></p>';
      }
    ?>
  </div>
