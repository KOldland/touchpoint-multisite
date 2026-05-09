<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
/* Portal full-width template reset — no theme chrome */
*,
*::before,
*::after { box-sizing: border-box; }

html,
body {
	margin: 0;
	padding: 0;
	background: #f4f6f9;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
	color: #1a1a2e;
	min-height: 100vh;
}

/* Portal chrome */
.khm-portal-chrome {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 24px;
	height: 56px;
	background: #fff;
	border-bottom: 1px solid #e2e8f0;
	position: sticky;
	top: 0;
	z-index: 200;
}

.khm-portal-chrome__brand {
	display: flex;
	align-items: center;
	gap: 10px;
	text-decoration: none;
	color: inherit;
	font-weight: 700;
	font-size: 1rem;
	letter-spacing: -0.01em;
}

.khm-portal-chrome__brand img {
	height: 32px;
	width: auto;
}

.khm-portal-chrome__brand-name {
	color: #1a1a2e;
}

.khm-portal-chrome__user {
	display: flex;
	align-items: center;
	gap: 14px;
	font-size: 0.875rem;
	color: #64748b;
}

.khm-portal-chrome__user a {
	color: #2563eb;
	text-decoration: none;
}

.khm-portal-chrome__user a:hover {
	text-decoration: underline;
}

/* Full-width content area */
.khm-portal-content {
	width: 100%;
	min-height: calc(100vh - 56px);
	padding-top: 2%;
	padding-bottom: 2%;
}
</style>
</head>
<body <?php body_class( 'khm-portal-full-width' ); ?>>
<?php wp_body_open(); ?>

<div class="khm-portal-chrome">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="khm-portal-chrome__brand">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<span class="khm-portal-chrome__brand-name"><?php bloginfo( 'name' ); ?></span>
		<?php endif; ?>
	</a>

	<div class="khm-portal-chrome__user">
		<?php if ( is_user_logged_in() ) : ?>
			<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'khm-portal' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'khm-portal' ); ?></a>
		<?php endif; ?>
	</div>
</div>

<div class="khm-portal-content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>

<?php wp_footer(); ?>
</body>
</html>
