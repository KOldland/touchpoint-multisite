<?php

namespace KHM\Elementor\Widgets;

use Elementor\Widget_Base;

/**
 * Quote Club search toolbar widget.
 */
class QuoteClubSearchToolbar_Widget extends Widget_Base {

    public function get_name() {
        return 'khm_quoteclub_search_toolbar';
    }

    public function get_title() {
        return __('Quote Club Search Toolbar', 'khm-membership');
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return ['touchpoint', 'theme-elements'];
    }

    public function get_keywords() {
        return ['quote club', 'search', 'toolbar', 'filters'];
    }

    protected function render() {
        $support = new QuoteClubWidgetSupport();
        $support->enqueue_assets();
        $top_line_categories = $this->get_top_line_categories();

        if (!is_user_logged_in()) {
            $support->render_login_required(__('Please log in to use Quote Club search.', 'khm-membership'));
            return;
        }
        ?>
        <div class="khm-quoteclub-toolbar" id="khm-qc-search-workspace">
            <select class="khm-filter-date-range" aria-label="<?php esc_attr_e('Date Range', 'khm-membership'); ?>">
                <option value="all"><?php esc_html_e('All', 'khm-membership'); ?></option>
                <option value="week"><?php esc_html_e('Within the next week', 'khm-membership'); ?></option>
                <option value="month"><?php esc_html_e('Within the next month', 'khm-membership'); ?></option>
            </select>
            <?php if (!empty($top_line_categories)) : ?>
            <select multiple class="khm-filter-categories" aria-label="<?php esc_attr_e('Categories', 'khm-membership'); ?>">
                <?php foreach ($top_line_categories as $category) : ?>
                    <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <div class="khm-topic-autocomplete">
                <input type="text" class="khm-filter-topics" autocomplete="off" placeholder="Topics" />
                <div class="khm-topic-suggest-menu" role="listbox" aria-label="<?php esc_attr_e('Topic suggestions', 'khm-membership'); ?>"></div>
            </div>
            <input type="text" class="khm-filter-keywords" placeholder="Keywords" />
            <select class="khm-filter-operator" aria-label="<?php esc_attr_e('Keyword operator', 'khm-membership'); ?>">
                <option value="AND"><?php esc_html_e('AND', 'khm-membership'); ?></option>
                <option value="OR"><?php esc_html_e('OR', 'khm-membership'); ?></option>
            </select>
            <p class="khm-filter-operator-help"><?php esc_html_e('Keyword match: AND requires all words, OR matches any word. Use AND to narrow and OR to broaden.', 'khm-membership'); ?></p>
            <select class="khm-saved-searches" aria-label="<?php esc_attr_e('Saved searches', 'khm-membership'); ?>">
                <option value=""><?php esc_html_e('— Saved Searches —', 'khm-membership'); ?></option>
            </select>
            <button type="button" class="button khm-quoteclub-search-btn"><?php esc_html_e('Search', 'khm-membership'); ?></button>
            <button type="button" class="button khm-save-search-btn"><?php esc_html_e('Save Search', 'khm-membership'); ?></button>
        </div>
        <?php
    }

    /**
     * Fetch top-line categories from the shared Dual GPT option.
     *
     * @return array<int, string>
     */
    private function get_top_line_categories(): array {
        $stored = get_option('dual_gpt_top_line_categories', null);
        if (!is_array($stored) || empty($stored)) {
            return [];
        }

        $categories = [];
        foreach ($stored as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $categories[] = $name;
            }
        }

        $categories = array_values(array_unique($categories));
        if (empty($categories)) {
            return [];
        }

        return $categories;
    }
}
