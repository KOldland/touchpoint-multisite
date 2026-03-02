<?php
$routes = rest_get_server()->get_routes();
if ( isset( $routes['/khm-geo/v1'] ) && isset( $routes['/khm-geo/v1']['/suggest-answercards'] ) ) {
    echo "registered\n";
    var_export( array_keys( $routes['/khm-geo/v1']['/suggest-answercards'] ) ); // show methods/args
} else {
    echo "not_registered\n";
    // show khm-geo namespace routes if any
    foreach ( $routes as $ns => $r ) {
        if ( strpos( $ns, 'khm-geo' ) !== false ) {
            echo "FOUND_NS: $ns\n";
            foreach ( $r as $rt => $meta ) {
                echo "  route: $rt\n";
            }
        }
    }
}
