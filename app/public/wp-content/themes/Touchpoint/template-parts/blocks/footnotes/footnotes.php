  <div class="kss-footnotes">
    <h3><u>Footnotes</u></h3>
    
    <?php
      $footnotes = get_field('footnotes');
      if ($footnotes) {
        echo '<ol>';
        foreach ($footnotes as $item) {
          $text = $item['reference_text'];
          $link = $item['reference_link'];
          
          if ($text) {
            echo '<li>';
            if ($link) {
              echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($text) . '</a>';
            } else {
              echo esc_html($text);
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

