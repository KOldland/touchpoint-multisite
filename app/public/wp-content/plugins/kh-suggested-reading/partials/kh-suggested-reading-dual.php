<?php
  if ( ! defined( 'ABSPATH' ) ) exit;
  
  $args = get_query_var( 'kh_sr_args', [] );
  
  if ( empty( $args['posts'] ) ) return;
  
  $block_partial = plugin_dir_path( __FILE__ ) . 'kh-suggested-reading-block.php';
  
  $position = $args['position'] ?? 'top';
  $layout   = $position === 'footer' ? 'grid' : 'stacked';
  $title    = $args['title'] ?? 'Suggested Reading';
  
  $posts = $args['posts'] ?? [];
  
  $top_posts    = array_slice( $posts, 0, 3 );
  $footer_posts = array_slice( $posts, 3, 4 ); // ← LIMIT to 4 max in footer
  
  $selected_posts = $position === 'footer' ? $footer_posts : $top_posts;
  
  $block_args = [
    'posts'    => $selected_posts,
    'layout'   => $layout,
    'title'    => $title,
    'position' => $position,
  ];

  
  if ( file_exists( $block_partial ) ) {
    include $block_partial;
  }

