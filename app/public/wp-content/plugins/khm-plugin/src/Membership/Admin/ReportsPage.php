<?php

namespace KHM\Membership\Admin;

class ReportsPage {
    public function __construct() {
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'khm-main-menu',
            'Membership Reports',
            'Membership Reports',
            'manage_options',
            'khm-membership-reports',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promotion_attribution';
        $posts_table = $wpdb->posts;
        $sponsors_table = $wpdb->prefix . 'khm_sponsors';
        $has_sponsors = $this->table_exists( $sponsors_table );
        $has_posts = $this->table_exists( $posts_table );

        $query = "SELECT p.*";

        if ( $has_posts ) {
            $query .= ", sp.post_title AS schedule_title";
        } else {
            $query .= ", NULL AS schedule_title";
        }

        if ( $has_sponsors ) {
            $query .= ", s.name AS sponsor_name";
        } else {
            $query .= ", NULL AS sponsor_name";
        }

        $query .= " FROM {$table_name} p";

        if ( $has_posts ) {
            $query .= " LEFT JOIN {$posts_table} sp ON sp.ID = p.schedule_id";
        }

        if ( $has_sponsors ) {
            $query .= " LEFT JOIN {$sponsors_table} s ON s.id = p.sponsor_id";
        }

        $query .= " ORDER BY p.created_at DESC";

        $results = $wpdb->get_results( $query, ARRAY_A );
        ?>
        <div class="wrap">
            <h1>Membership Reports</h1>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th class="manage-column column-columnname" scope="col">ID</th>
                        <th class="manage-column column-columnname" scope="col">Schedule ID</th>
                        <th class="manage-column column-columnname" scope="col">Sponsor ID</th>
                        <th class="manage-column column-columnname" scope="col">User ID</th>
                        <th class="manage-column column-columnname" scope="col">User Email</th>
                        <th class="manage-column column-columnname" scope="col">Conversion Type</th>
                        <th class="manage-column column-columnname" scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['id']); ?></td>
                                <td>
                                    <?php
                                    $schedule_id = isset( $row['schedule_id'] ) ? absint( $row['schedule_id'] ) : 0;
                                    $schedule_title = isset( $row['schedule_title'] ) ? trim( (string) $row['schedule_title'] ) : '';
                                    if ( $schedule_id > 0 ) {
                                        $label = $schedule_title ? sprintf( '%s (#%d)', $schedule_title, $schedule_id ) : sprintf( '#%d', $schedule_id );
                                        $schedule_link = get_edit_post_link( $schedule_id, '' );
                                        if ( $schedule_link ) {
                                            echo '<a href="' . esc_url( $schedule_link ) . '">' . esc_html( $label ) . '</a>';
                                        } else {
                                            echo esc_html( $label );
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $sponsor_id = isset( $row['sponsor_id'] ) ? absint( $row['sponsor_id'] ) : 0;
                                    $sponsor_name = isset( $row['sponsor_name'] ) ? trim( (string) $row['sponsor_name'] ) : '';
                                    if ( $sponsor_id > 0 ) {
                                        $label = $sponsor_name ? sprintf( '%s (#%d)', $sponsor_name, $sponsor_id ) : sprintf( '#%d', $sponsor_id );
                                        $sponsor_link = admin_url( 'admin.php?page=khm-sponsor-library' );
                                        echo '<a href="' . esc_url( $sponsor_link ) . '">' . esc_html( $label ) . '</a>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($row['user_id']); ?></td>
                                <td><?php echo esc_html($row['user_email']); ?></td>
                                <td><?php echo esc_html($row['conversion_type']); ?></td>
                                <td><?php echo esc_html($row['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="7">No attribution data found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function table_exists( string $table ): bool {
        global $wpdb;
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        return $found === $table;
    }
}
