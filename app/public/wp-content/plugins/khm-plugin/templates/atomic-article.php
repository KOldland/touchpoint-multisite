<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( get_the_title() ); ?> | <?php bloginfo( 'name' ); ?></title>

<?php
// Canonical self-URL — essential for GEO crawlers.
echo '<link rel="canonical" href="' . esc_url( get_permalink() ) . '">' . "\n";

// Back-link to the parent post for context.
$parent_id = (int) get_post_meta( get_the_ID(), '_atomic_parent_id', true );
if ( $parent_id ) {
    echo '<link rel="up" href="' . esc_url( get_permalink( $parent_id ) ) . '">' . "\n";
}

// JSON-LD is emitted by AtomicSchemaEmitter via wp_head.
wp_head();
?>
<style>
/* Atomic article: deliberately minimal. No theme chrome, no distractions.
   Only what GEO crawlers and humans need to read the content. */
*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    padding: 2rem 1rem;
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 1.125rem;
    line-height: 1.75;
    color: #1a1a1a;
    background: #fff;
}

main {
    max-width: 720px;
    margin: 0 auto;
}

h1 {
    font-size: 2rem;
    line-height: 1.25;
    margin: 0 0 0.5rem;
}

h2 {
    font-size: 1.375rem;
    line-height: 1.3;
    margin: 2rem 0 0.5rem;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 0.25rem;
}

p { margin: 0 0 1.25rem; }

.atomic-meta {
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 2rem;
}

.atomic-meta a { color: #555; }

.atomic-schema-badge {
    display: inline-block;
    font-size: 0.75rem;
    font-family: monospace;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 1px 6px;
    margin-left: 0.5rem;
    vertical-align: middle;
    color: #444;
}
</style>
</head>
<body <?php body_class( 'atomic-article' ); ?>>

<main id="atomic-content">

<?php
if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();

        $post_id     = get_the_ID();
        $schema_type = get_post_meta( $post_id, '_atomic_schema_type', true ) ?: 'Article';
        $parent_id   = (int) get_post_meta( $post_id, '_atomic_parent_id', true );
        $generated   = get_post_meta( $post_id, '_atomic_generated_at', true );
?>

<h1><?php the_title(); ?><span class="atomic-schema-badge"><?php echo esc_html( $schema_type ); ?></span></h1>

<div class="atomic-meta">
<?php if ( $parent_id ) : ?>
    Part of: <a href="<?php echo esc_url( get_permalink( $parent_id ) ); ?>"><?php echo esc_html( get_the_title( $parent_id ) ); ?></a>
    &nbsp;·&nbsp;
<?php endif; ?>
<?php if ( $generated ) : ?>
    Generated <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $generated ) ) ); ?>
<?php endif; ?>
</div>

<?php if ( has_excerpt() ) : ?>
<p><strong><?php the_excerpt(); ?></strong></p>
<?php endif; ?>

<div class="atomic-body">
    <?php the_content(); ?>
</div>

<?php
    endwhile;
endif;
?>

</main>

<?php wp_footer(); ?>
</body>
</html>
