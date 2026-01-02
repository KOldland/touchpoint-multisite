<?php
/**
 * The template for displaying the footer.
 *
 * Contains the body & html closing tags.
 *
 * @package Touchpoint CRM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load Elementor footer if available.
if ( function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( 'footer' ) ) {
	return;
}
	
// Fallback footer template part if not handled by Elementor.
get_template_part( 'template-parts/footer' );
?>

<?php wp_footer(); ?>
</body>
</html>
