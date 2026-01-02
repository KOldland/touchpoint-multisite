<?php
/**
 * Entity Edit Admin Template
 * 
 * Provides a form for editing entity details and managing aliases.
 * @package KHM_SEO\GEO\Templates
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Assume $entity is provided by controller
?>
<div class="wrap khm-geo-entity-edit">
    <h1><?php echo esc_html( $entity['canonical'] ?? __( 'Edit Entity', 'khm-seo' ) ); ?></h1>
    <form method="post" action="">
        <?php wp_nonce_field( 'khm_geo_edit_entity', 'khm_geo_edit_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th><label for="canonical"><?php _e( 'Canonical Name', 'khm-seo' ); ?></label></th>
                <td><input type="text" id="canonical" name="canonical" value="<?php echo esc_attr( $entity['canonical'] ?? '' ); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="type"><?php _e( 'Type', 'khm-seo' ); ?></label></th>
                <td>
                    <select id="type" name="type">
                        <?php foreach ( $valid_types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $entity['type'] ?? '', $type ); ?>><?php echo esc_html( $type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="scope"><?php _e( 'Scope', 'khm-seo' ); ?></label></th>
                <td>
                    <select id="scope" name="scope">
                        <?php foreach ( $valid_scopes as $scope ) : ?>
                            <option value="<?php echo esc_attr( $scope ); ?>" <?php selected( $entity['scope'] ?? '', $scope ); ?>><?php echo esc_html( ucfirst( $scope ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="status"><?php _e( 'Status', 'khm-seo' ); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <?php foreach ( $valid_statuses as $status ) : ?>
                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $entity['status'] ?? '', $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aliases"><?php _e( 'Aliases', 'khm-seo' ); ?></label></th>
                <td>
                    <div id="khm-geo-alias-list">
                        <?php if ( !empty( $entity['aliases'] ) ) : ?>
                            <?php foreach ( $entity['aliases'] as $i => $alias ) : ?>
                                <div class="khm-geo-alias-row">
                                    <input type="text" name="aliases[]" value="<?php echo esc_attr( $alias ); ?>" class="regular-text" />
                                    <button type="button" class="button khm-geo-remove-alias" onclick="this.parentNode.remove();">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" onclick="khmGeoAddAliasRow();"><?php _e( 'Add Alias', 'khm-seo' ); ?></button>
                    <p class="description"><?php _e( 'Manage aliases for this entity. Use Add Alias to create new rows.', 'khm-seo' ); ?></p>
                    <script>
                    function khmGeoAddAliasRow() {
                        var container = document.getElementById('khm-geo-alias-list');
                        var row = document.createElement('div');
                        row.className = 'khm-geo-alias-row';
                        row.innerHTML = '<input type="text" name="aliases[]" class="regular-text" /> <button type="button" class="button khm-geo-remove-alias" onclick="this.parentNode.remove();">&times;</button>';
                        container.appendChild(row);
                    }
                    </script>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'khm-seo' ); ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=khm-seo-entities' ) ); ?>" class="button-secondary"><?php _e( 'Cancel', 'khm-seo' ); ?></a>
        </p>
    </form>
</div>
