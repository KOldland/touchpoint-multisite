<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'grassblade/reports/js/gb_reports', 'kh_xapi_register_reports' );
add_filter( 'grassblade/reports/js/functions', 'kh_xapi_report_bootstrap' );
add_filter( 'grassblade/reports/scripts', 'kh_xapi_report_scripts' );

function kh_xapi_register_reports( $reports ) {
    $reports['completion_summary'] = [
        'label'    => __( 'Completion Summary', 'kh-xapi' ),
        'endpoint' => rest_url( 'kh-xapi/v1/reports' ),
        'params'   => [ 'summary' => true ],
        'export'   => rest_url( 'kh-xapi/v1/reports/export' ),
    ];

    $reports['completion_rows'] = [
        'label'    => __( 'Completion Rows', 'kh-xapi' ),
        'endpoint' => rest_url( 'kh-xapi/v1/reports' ),
        'params'   => [],
        'export'   => rest_url( 'kh-xapi/v1/reports/export' ),
        'headers'  => [ 'content_id', 'user_id', 'status', 'percentage', 'score', 'timespent', 'registration', 'recorded_at' ],
    ];

    $reports['content_performance'] = [
        'label'    => __( 'Content Performance', 'kh-xapi' ),
        'endpoint' => rest_url( 'kh-xapi/v1/reports/aggregate' ),
        'params'   => [ 'dimension' => 'content' ],
        'export'   => rest_url( 'kh-xapi/v1/reports/export' ),
        'headers'  => [ 'content_id', 'total', 'completed', 'avg_percent' ],
    ];

    $reports['user_progress'] = [
        'label'    => __( 'User Progress Overview', 'kh-xapi' ),
        'endpoint' => rest_url( 'kh-xapi/v1/reports/aggregate' ),
        'params'   => [ 'dimension' => 'user' ],
        'export'   => rest_url( 'kh-xapi/v1/reports/export' ),
        'headers'  => [ 'user_id', 'total', 'completed', 'avg_score' ],
    ];

    $reports['status_distribution'] = [
        'label'    => __( 'Status Distribution', 'kh-xapi' ),
        'endpoint' => rest_url( 'kh-xapi/v1/reports/aggregate' ),
        'params'   => [ 'dimension' => 'status' ],
        'export'   => rest_url( 'kh-xapi/v1/reports/export' ),
        'headers'  => [ 'status', 'total' ],
    ];

    return $reports;
}

function kh_xapi_report_bootstrap( $data ) {
    $data['rest'] = [
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ];
    return $data;
}

function kh_xapi_report_scripts( $scripts ) {
    wp_register_script( 'kh-xapi-reports', KH_XAPI_URL . 'assets/js/reports.js', [], KH_XAPI_VERSION, true );
    wp_register_style( 'kh-xapi-reports', KH_XAPI_URL . 'assets/css/reports.css', [], KH_XAPI_VERSION );

    wp_enqueue_script( 'kh-xapi-reports' );
    wp_enqueue_style( 'kh-xapi-reports' );

    return $scripts;
}
