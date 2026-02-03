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
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
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
                                <td><?php echo esc_html($row['schedule_id']); ?></td>
                                <td><?php echo esc_html($row['sponsor_id']); ?></td>
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
}
