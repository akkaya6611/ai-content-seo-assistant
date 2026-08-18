<?php
/**
 * Frontend SEO, OpenGraph & Schema.org JSON-LD Yapısal Veri Motoru
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Engine {

    private $options;

    public function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->init_hooks();
    }

    private function init_hooks() {
        // Eğer diğer büyük SEO eklentileri aktif değilse meta etiketlerini yönet
        if (!$this->is_third_party_seo_active()) {
            add_action('wp_head', array($this, 'render_meta_tags'), 1);
            add_filter('pre_get_document_title', array($this, 'filter_document_title'), 15);
        }

        // Schema.org JSON-LD
        if (!empty($this->options['enable_schema'])) {
            add_action('wp_head', array($this, 'render_json_ld_schema'), 20);
        }
    }

    /**
     * Başka bir SEO eklentisi (Yoast, RankMath, AIOSEO) aktif mi kontrol et
     */
    public function is_third_party_seo_active() {
        return defined('WPSEO_VERSION') || // Yoast SEO
               defined('RANK_MATH_VERSION') || // Rank Math
               defined('AIOSEO_VERSION'); // All in One SEO
    }

    /**
     * Özel SEO Meta Başlığını Filtrele
     */
    public function filter_document_title($title) {
        if (is_singular()) {
            global $post;
            if ($post) {
                $custom_title = get_post_meta($post->ID, '_ai_seo_meta_title', true);
                if (!empty($custom_title)) {
                    return wp_strip_all_tags($custom_title);
                }
            }
        }
        return $title;
    }

    /**
     * Frontend için Meta Etiketlerini (Description, OG, Twitter) Bas
     */
    public function render_meta_tags() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $post_id = $post->ID;
        $meta_title = get_post_meta($post_id, '_ai_seo_meta_title', true);
        $meta_desc = get_post_meta($post_id, '_ai_seo_meta_desc', true);
        $meta_keywords = get_post_meta($post_id, '_ai_seo_focus_keyword', true);
        $canonical_url = get_permalink($post_id);

        if (empty($meta_title)) {
            $meta_title = get_the_title($post_id);
        }

        if (empty($meta_desc)) {
            $meta_desc = wp_strip_all_tags(strip_shortcodes($post->post_excerpt ?: $post->post_content));
            $meta_desc = mb_substr($meta_desc, 0, 160, 'UTF-8');
        }

        // Öne çıkan görsel
        $image_url = '';
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'full');
        }

        echo "\n<!-- AI Content & SEO Assistant Meta Tags -->\n";

        // Meta Description & Keywords
        if (!empty($meta_desc)) {
            echo '<meta name="description" content="' . esc_attr($meta_desc) . '" />' . "\n";
        }
        if (!empty($meta_keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($meta_keywords) . '" />' . "\n";
        }
        echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";

        // OpenGraph
        if (!empty($this->options['enable_opengraph'])) {
            echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '" />' . "\n";
            echo '<meta property="og:type" content="' . (is_single() ? 'article' : 'website') . '" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($meta_title) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($meta_desc) . '" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url($canonical_url) . '" />' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";
            if (!empty($image_url)) {
                echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
            }
            if (is_single()) {
                echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post_id)) . '" />' . "\n";
                echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post_id)) . '" />' . "\n";
            }
        }

        // Twitter Cards
        if (!empty($this->options['enable_twitter'])) {
            echo '<meta name="twitter:card" content="' . (!empty($image_url) ? 'summary_large_image' : 'summary') . '" />' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($meta_title) . '" />' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($meta_desc) . '" />' . "\n";
            if (!empty($image_url)) {
                echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
            }
        }

        echo "<!-- / AI Content & SEO Assistant Meta Tags -->\n\n";
    }

    /**
     * Schema.org JSON-LD Yapısal Verisini Oluştur ve Bas
     */
    public function render_json_ld_schema() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $post_id = $post->ID;
        $meta_title = get_post_meta($post_id, '_ai_seo_meta_title', true) ?: get_the_title($post_id);
        $meta_desc = get_post_meta($post_id, '_ai_seo_meta_desc', true);
        if (empty($meta_desc)) {
            $meta_desc = wp_strip_all_tags(strip_shortcodes($post->post_excerpt ?: $post->post_content));
            $meta_desc = mb_substr($meta_desc, 0, 160, 'UTF-8');
        }

        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        $author_url = get_author_posts_url($author_id);

        $image_url = '';
        if (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'full');
        }

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => is_single() ? 'BlogPosting' : 'Article',
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => get_permalink($post_id),
            ),
            'headline'         => $meta_title,
            'description'      => $meta_desc,
            'datePublished'    => get_the_date('c', $post_id),
            'dateModified'     => get_the_modified_date('c', $post_id),
            'author'           => array(
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => $author_url,
            ),
            'publisher'        => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url(),
            ),
        );

        if (!empty($image_url)) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url'   => $image_url,
            );
        }

        $site_icon = get_site_icon_url();
        if (!empty($site_icon)) {
            $schema['publisher']['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $site_icon,
            );
        }

        $schema = apply_filters('ai_seo_json_ld_schema', $schema, $post_id);

        $json_flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $json_flags |= JSON_PRETTY_PRINT;
        }

        echo "\n<!-- AI Content & SEO Assistant Schema JSON-LD -->\n";
        echo '<script type="application/ld+json">' . wp_json_encode($schema, $json_flags) . '</script>' . "\n";
        echo "<!-- / AI Content & SEO Assistant Schema JSON-LD -->\n\n";
    }
}
