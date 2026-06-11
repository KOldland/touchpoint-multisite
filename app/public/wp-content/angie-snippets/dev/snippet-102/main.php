<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

const NEWS_HERO_CAROUSEL_ASSETS_VERSION_d8c6e538 = '1.0.0';

function register_news_hero_carousel_widget_d8c6e538( $widgets_manager ) {
    require_once __DIR__ . '/widget-news-hero-carousel.php';
    $widgets_manager->register( new \AngieSnippets\News_Hero_Carousel_d8c6e538() );
}
add_action( 'elementor/widgets/register', 'register_news_hero_carousel_widget_d8c6e538' );

function register_news_hero_carousel_assets_d8c6e538() {
	wp_register_script( 'news-hero-carousel-script-d8c6e538', angie_cs_get_snippet_asset_url( __FILE__, 'script.js' ), [ 'elementor-frontend' ], NEWS_HERO_CAROUSEL_ASSETS_VERSION_d8c6e538, true );
	wp_register_style( 'news-hero-carousel-style-d8c6e538', angie_cs_get_snippet_asset_url( __FILE__, 'style.css' ), [], NEWS_HERO_CAROUSEL_ASSETS_VERSION_d8c6e538 );
}
add_action( 'wp_enqueue_scripts', 'register_news_hero_carousel_assets_d8c6e538' );
