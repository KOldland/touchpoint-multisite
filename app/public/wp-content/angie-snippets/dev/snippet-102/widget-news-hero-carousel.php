<?php
namespace AngieSnippets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class News_Hero_Carousel_d8c6e538 extends \Elementor\Widget_Base {

    public function get_name() { return 'news_hero_carousel_d8c6e538'; }
    public function get_title() { return esc_html__( 'News Hero Carousel', 'angie-snippets' ); }
    public function get_icon() { return 'eicon-slides'; }
    public function get_categories() { return [ 'angie-widgets', 'general' ]; }
    public function get_script_depends() { return [ 'news-hero-carousel-script-d8c6e538' ]; }
    public function get_style_depends() { return [ 'news-hero-carousel-style-d8c6e538' ]; }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__( 'Content', 'angie-snippets' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'posts_per_page',
            [
                'label' => esc_html__( 'Number of Posts', 'angie-snippets' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 10,
            ]
        );

        $this->add_control(
            'category_filter',
            [
                'label' => esc_html__( 'Category Slug (Optional)', 'angie-snippets' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'e.g. news', 'angie-snippets' ),
            ]
        );
        
        $this->add_control(
            'height',
            [
                'label' => esc_html__( 'Height (vh)', 'angie-snippets' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'vh', 'px' ],
                'range' => [
                    'vh' => [ 'min' => 20, 'max' => 100 ],
                    'px' => [ 'min' => 200, 'max' => 1000 ],
                ],
                'default' => [ 'unit' => 'vh', 'size' => 70 ],
                'selectors' => [
                    '{{WRAPPER}} .nhc-d8c6e538-container' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style_title',
            [
                'label' => esc_html__( 'Title & Overlay', 'angie-snippets' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'overlay_color',
            [
                'label' => esc_html__( 'Overlay Color', 'angie-snippets' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .nhc-d8c6e538-slide::before' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__( 'Title Color', 'angie-snippets' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .nhc-d8c6e538-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .nhc-d8c6e538-title',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $settings['posts_per_page'],
            'post_status' => 'publish',
        ];

        if ( ! empty( $settings['category_filter'] ) ) {
            $args['category_name'] = sanitize_title( $settings['category_filter'] );
        }

        $query = new \WP_Query( $args );
        ?>
        <div class="nhc-d8c6e538-wrapper">
            <div class="nhc-d8c6e538-container">
                <?php if ( $query->have_posts() ) : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); 
                        $image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                        if ( ! $image_url ) {
                            $image_url = 'https://via.placeholder.com/1920x1080?text=No+Featured+Image';
                        }
                    ?>
                    <div class="nhc-d8c6e538-slide" style="background-image: url('<?php echo esc_url( $image_url ); ?>');">
                        <div class="nhc-d8c6e538-content">
                            <h2 class="nhc-d8c6e538-title">
                                <a href="<?php echo esc_url( get_permalink() ); ?>">
                                    <?php echo esc_html( get_the_title() ); ?>
                                </a>
                            </h2>
                        </div>
                    </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                <?php else: ?>
                    <div class="nhc-d8c6e538-slide" style="background-image: url('https://via.placeholder.com/1920x1080?text=No+Posts+Found');">
                        <div class="nhc-d8c6e538-content">
                            <h2 class="nhc-d8c6e538-title"><?php esc_html_e( 'No posts found.', 'angie-snippets' ); ?></h2>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button class="nhc-d8c6e538-nav nhc-d8c6e538-prev" aria-label="Previous Slide">
                &#10094;
            </button>
            <button class="nhc-d8c6e538-nav nhc-d8c6e538-next" aria-label="Next Slide">
                &#10095;
            </button>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <div class="nhc-d8c6e538-wrapper">
            <div class="nhc-d8c6e538-container" style="height: {{ settings.height.size }}{{ settings.height.unit }};">
                <# for ( var i = 0; i < settings.posts_per_page; i++ ) { #>
                    <div class="nhc-d8c6e538-slide" style="background-image: url('https://via.placeholder.com/1920x1080?text=Preview+Article+{{ i + 1 }}');">
                        <div class="nhc-d8c6e538-content">
                            <h2 class="nhc-d8c6e538-title">Preview Article Title {{ i + 1 }}</h2>
                        </div>
                    </div>
                <# } #>
            </div>
            <button class="nhc-d8c6e538-nav nhc-d8c6e538-prev" aria-label="Previous Slide">&#10094;</button>
            <button class="nhc-d8c6e538-nav nhc-d8c6e538-next" aria-label="Next Slide">&#10095;</button>
        </div>
        <?php
    }
}
