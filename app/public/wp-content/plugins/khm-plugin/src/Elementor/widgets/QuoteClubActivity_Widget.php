<?php

namespace KHM\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * Quote Club recent activity table widget.
 */
class QuoteClubActivity_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_activity';
    }

    public function get_title() {
        return __('Quote Club Activity Table', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-table';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'activity', 'table', 'status', 'pagination'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Activity', 'khm-membership'),
            ]
        );

        $this->add_control(
            'default_rows_per_page',
            [
                'label' => __('Default Rows Per Page', 'khm-membership'),
                'type' => Controls_Manager::SELECT,
                'default' => '10',
                'options' => [
                    '10' => '10',
                    '20' => '20',
                    '50' => '50',
                    '100' => '100',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to view Quote Club activity.', 'khm-membership'));
            return;
        }

        $default_per_page = (int) ($this->get_settings_for_display()['default_rows_per_page'] ?? 10);
        $ctx = $support->get_activity_context(get_current_user_id(), $default_per_page);
        ?>
        <div class="khm-qc-member-recent">
            <h3><?php esc_html_e('Recent Activity', 'khm-membership'); ?></h3>
            <div class="khm-qc-member-recent-controls">
                <form method="get" class="khm-qc-member-page-size-form">
                    <input type="hidden" name="qc_activity_page" value="1" />
                    <label for="khm-qc-activity-per-page"><?php esc_html_e('Rows per page', 'khm-membership'); ?></label>
                    <select id="khm-qc-activity-per-page" name="qc_activity_per_page" onchange="this.form.submit()">
                        <?php foreach ($ctx['allowed_per_page'] as $size) : ?>
                            <option value="<?php echo esc_attr($size); ?>" <?php selected($ctx['activity_per_page'], $size); ?>>
                                <?php echo esc_html($size); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (!empty($ctx['recent'])) : ?>
                <div class="khm-qc-member-table-wrap">
                    <table class="khm-qc-member-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'khm-membership'); ?></th>
                                <th><?php esc_html_e('Item', 'khm-membership'); ?></th>
                                <th><?php esc_html_e('Status', 'khm-membership'); ?></th>
                                <th><?php esc_html_e('Date', 'khm-membership'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ctx['recent'] as $item) :
                                $activity_type = sanitize_key((string) ($item['activity_type'] ?? 'commentary'));
                                $status_key = sanitize_key((string) ($item['status'] ?? ''));
                                $status_label = $support->map_activity_status($status_key);
                            ?>
                                <tr>
                                    <td>
                                        <span class="khm-qc-member-type"><?php echo esc_html($activity_type === 'press_release' ? __('Press Release', 'khm-membership') : __('Commentary', 'khm-membership')); ?></span>
                                    </td>
                                    <td>
                                        <span class="khm-qc-member-session"><?php echo esc_html((string) ($item['activity_label'] ?? 'Goose Egg')); ?></span>
                                    </td>
                                    <td>
                                        <span class="khm-qc-member-status khm-qc-member-status-<?php echo esc_attr($status_label['class']); ?>"><?php echo esc_html($status_label['label']); ?></span>
                                    </td>
                                    <td>
                                        <span class="khm-qc-member-date"><?php echo esc_html(substr((string) ($item['activity_at'] ?? ''), 0, 10)); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($ctx['activity_total_pages'] > 1) : ?>
                    <nav class="khm-qc-member-pagination" aria-label="<?php esc_attr_e('Recent activity pages', 'khm-membership'); ?>">
                        <?php if ($ctx['activity_page'] > 1) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg([
                                'qc_activity_page' => $ctx['activity_page'] - 1,
                                'qc_activity_per_page' => $ctx['activity_per_page'],
                            ], $ctx['pagination_base_url'])); ?>"><?php esc_html_e('Previous', 'khm-membership'); ?></a>
                        <?php endif; ?>

                        <?php for ($page = $ctx['page_window_start']; $page <= $ctx['page_window_end']; $page++) : ?>
                            <?php if ($page === $ctx['activity_page']) : ?>
                                <span class="button khm-qc-page-current" aria-current="page"><?php echo esc_html($page); ?></span>
                            <?php else : ?>
                                <a class="button" href="<?php echo esc_url(add_query_arg([
                                    'qc_activity_page' => $page,
                                    'qc_activity_per_page' => $ctx['activity_per_page'],
                                ], $ctx['pagination_base_url'])); ?>"><?php echo esc_html($page); ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($ctx['activity_page'] < $ctx['activity_total_pages']) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg([
                                'qc_activity_page' => $ctx['activity_page'] + 1,
                                'qc_activity_per_page' => $ctx['activity_per_page'],
                            ], $ctx['pagination_base_url'])); ?>"><?php esc_html_e('Next', 'khm-membership'); ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <p class="khm-qc-member-empty"><?php esc_html_e('No activity yet. Get started by submitting your first quote!', 'khm-membership'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
