<?php

namespace KH\EditorialAuthor\Services;

/**
 * GutenbergCompiler — converts structured block arrays into Gutenberg
 * <!-- wp: --> comment markup so the block editor renders each block
 * as a separate, individually editable element.
 *
 * Input format (from DraftAgent/EnrichmentAgent):
 *   [
 *     ['type' => 'heading',   'level' => 2, 'content' => '...'],
 *     ['type' => 'paragraph', 'content' => '...'],
 *     ['type' => 'list',      'ordered' => false, 'items' => ['...', '...']],
 *     ['type' => 'pullquote', 'content' => '...', 'cite' => '...'],
 *     ['type' => 'separator'],
 *     ['type' => 'image',     'url' => '...', 'alt' => '...'],
 *   ]
 *
 * Output: Gutenberg block markup string suitable for post_content.
 */
class GutenbergCompiler {

    /**
     * Compile a blocks array into Gutenberg block markup.
     *
     * @param array $blocks  Array of block definitions.
     * @return string        Gutenberg-compatible HTML with <!-- wp: --> comments.
     */
    public function compile(array $blocks): string {
        $output = '';

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'paragraph';

            $method = 'compile_' . str_replace('-', '_', $type);
            if (method_exists($this, $method)) {
                $output .= $this->$method($block) . "\n\n";
            } else {
                // Fallback: render as paragraph
                $output .= $this->compile_paragraph($block) . "\n\n";
            }
        }

        return trim($output);
    }

    // -------------------------------------------------------------------------
    //  Block compilers
    // -------------------------------------------------------------------------

    private function compile_heading(array $block): string {
        $level  = $block['level'] ?? 2;
        $attrs  = json_encode(['level' => $level], JSON_UNESCAPED_SLASHES);
        $html   = sprintf('<h%d>%s</h%d>', $level, $this->escape($block['content'] ?? ''), $level);

        return sprintf(
            "<!-- wp:heading %s -->\n%s\n<!-- /wp:heading -->",
            $attrs,
            $html
        );
    }

    private function compile_paragraph(array $block): string {
        $content = $this->escape($block['content'] ?? '');

        return sprintf(
            "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
            $content
        );
    }

    private function compile_list(array $block): string {
        $ordered = !empty($block['ordered']);
        $tag     = $ordered ? 'ol' : 'ul';
        $items   = $block['items'] ?? [];

        $lis = '';
        foreach ($items as $item) {
            $lis .= sprintf('<li>%s</li>', $this->escape($item));
        }

        $html = sprintf('<%s>%s</%s>', $tag, $lis, $tag);

        return sprintf(
            "<!-- wp:list -->\n%s\n<!-- /wp:list -->",
            $html
        );
    }

    private function compile_pullquote(array $block): string {
        $cite = !empty($block['cite'])
            ? sprintf('<cite>%s</cite>', $this->escape($block['cite']))
            : '';

        $html = sprintf(
            '<blockquote class="wp-block-quote"><p>%s</p>%s</blockquote>',
            $this->escape($block['content'] ?? ''),
            $cite
        );

        return sprintf(
            "<!-- wp:quote -->\n%s\n<!-- /wp:quote -->",
            $html
        );
    }

    /**
     * Alias: the Pullquote agent block renders as a Gutenberg quote.
     */
    private function compile_quote(array $block): string {
        return $this->compile_pullquote($block);
    }

    private function compile_separator(array $block): string {
        return "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
    }

    private function compile_image(array $block): string {
        $url = $block['url'] ?? '';
        $alt = $this->escape($block['alt'] ?? '');

        $attrs = json_encode([
            'url' => $url,
            'alt' => $alt,
        ], JSON_UNESCAPED_SLASHES);

        $html = sprintf(
            '<figure class="wp-block-image"><img src="%s" alt="%s"/></figure>',
            esc_url($url),
            $alt
        );

        return sprintf(
            "<!-- wp:image %s -->\n%s\n<!-- /wp:image -->",
            $attrs,
            $html
        );
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Escape content for safe HTML output inside Gutenberg blocks.
     */
    private function escape(string $text): string {
        // Use esc_html for safe inline output; preserve entities.
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }
}