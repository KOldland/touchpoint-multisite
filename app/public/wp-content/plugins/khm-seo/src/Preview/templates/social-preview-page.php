<?php
/**
 * Social Preview Admin Page Template
 *
 * @package KHM_SEO
 * @subpackage Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Social Media Previews', 'khm-seo' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Generate social previews for published content to keep the editor fast.', 'khm-seo' ); ?>
    </p>

    <form method="get">
        <input type="hidden" name="page" value="khm-seo-social-preview" />

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="khm-social-preview-post-type"><?php esc_html_e( 'Content Type', 'khm-seo' ); ?></label>
                    </th>
                    <td>
                        <select id="khm-social-preview-post-type" name="post_type">
                            <?php foreach ( $post_types as $type_slug => $type_obj ) : ?>
                                <option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $selected_type, $type_slug ); ?>>
                                    <?php echo esc_html( $type_obj->labels->singular_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="khm-social-preview-post-id"><?php esc_html_e( 'Published Item', 'khm-seo' ); ?></label>
                    </th>
                    <td>
                        <select id="khm-social-preview-post-id" name="post_id">
                            <option value="0"><?php esc_html_e( 'Select a published item...', 'khm-seo' ); ?></option>
                            <?php foreach ( $posts as $post_item ) : ?>
                                <option value="<?php echo esc_attr( $post_item->ID ); ?>" <?php selected( $selected_post && $selected_post->ID === $post_item->ID ); ?>>
                                    <?php echo esc_html( $post_item->post_title ?: sprintf( __( '(Untitled #%d)', 'khm-seo' ), $post_item->ID ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button( __( 'Load Preview', 'khm-seo' ), 'primary', false ); ?>
    </form>

    <?php if ( $selected_post ) : ?>
        <?php
        global $post;
        $post = $selected_post;
        setup_postdata( $post );
        $preview_manager = $this;
        $platforms = $this->get_platforms();
        ?>

        <hr />
        <?php include KHM_SEO_PLUGIN_DIR . 'src/Preview/templates/preview-meta-box.php'; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <div class="notice notice-info" style="margin-top:16px;">
            <p><?php esc_html_e( 'Choose a published item to render social previews.', 'khm-seo' ); ?></p>
        </div>
    <?php endif; ?>
</div>
