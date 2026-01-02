<?php
if ( ! function_exists( 'gb_get_json_script' ) ) {
    function gb_get_json_script( string $handle, $data ): string {
        return sprintf(
            '<script id="%1$s" type="application/json">%2$s</script>',
            esc_attr( $handle ),
            wp_json_encode( $data )
        );
    }
}

if ( ! function_exists( 'gb_get_scripts' ) ) {
    function gb_get_scripts( array $scripts ): string {
        $html = '';
        foreach ( $scripts as $script ) {
            if ( is_array( $script ) ) {
                $src    = $script['src'] ?? '';
                $deps   = $script['deps'] ?? [];
                $handle = $script['handle'] ?? sanitize_title( $src );
                if ( $src ) {
                    wp_register_script( $handle, $src, $deps, null, true );
                    wp_enqueue_script( $handle );
                }
            } elseif ( is_string( $script ) ) {
                $html .= sprintf( '<script src="%s"></script>', esc_url( $script ) );
            }
        }
        return $html;
    }
}
