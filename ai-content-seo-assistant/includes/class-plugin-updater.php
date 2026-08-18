<?php
/**
 * Otomatik Güncelleme Motoru (Auto-Updater Engine)
 *
 * Bu sınıf, WordPress standart güncelleme kancalarını (pre_set_site_transient_update_plugins,
 * plugins_api ve upgrader_source_selection) dinleyerek, GitHub Releases veya
 * misteknoloji360.com.tr üzerindeki merkezi JSON dosyasından yeni sürümleri otomatik çeker.
 *
 * @package AI_Content_SEO_Assistant
 * @author  Serkan AKKAYA <https://misteknoloji360.com.tr/>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Plugin_Updater {

    /**
     * Eklenti ana dosya yolu
     */
    private $plugin_file;

    /**
     * Eklenti slug (örn: ai-content-seo-assistant/ai-content-seo-assistant.php)
     */
    private $plugin_basename;

    /**
     * Eklenti klasör adı (ai-content-seo-assistant)
     */
    private $plugin_slug;

    /**
     * Mevcut kurulu sürüm
     */
    private $current_version;

    /**
     * Uzak güncelleme sunucusu JSON uç noktası
     */
    private $update_url;

    public function __construct($plugin_file, $current_version, $update_url = '') {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug     = dirname($this->plugin_basename);
        $this->current_version = $current_version;

        // Varsayılan güncelleme JSON endpoint'i (GitHub veya misteknoloji360)
        $opts = get_option('ai_seo_assistant_options', array());
        $this->update_url = !empty($opts['update_server_url']) ? esc_url_raw($opts['update_server_url']) : ($update_url ?: 'https://raw.githubusercontent.com/akkaya6611/ai-content-seo-assistant/main/update-info.json');

        // WordPress Güncelleme Filtreleri
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));
        add_filter('plugins_api', array($this, 'plugin_popup_information'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_source_directory_name'), 10, 4);

        // Güncelleme sonrası önbelleği temizle
        add_action('upgrader_process_complete', array($this, 'clear_update_cache'), 10, 2);
    }

    /**
     * Uzak sunucudaki sürüm bilgilerini çeker
     */
    public function get_remote_version_info($force_check = false) {
        $cache_key = 'ai_seo_remote_update_info';
        $cached = get_transient($cache_key);

        if ($cached !== false && !$force_check) {
            return $cached;
        }

        $request_url = add_query_arg('t', time(), $this->update_url);
        $response = wp_remote_get($request_url, array(
            'timeout'   => 15,
            'sslverify' => apply_filters('ai_seo_sslverify', true),
            'headers'   => array(
                'Accept'        => 'application/json',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma'        => 'no-cache',
                'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || empty($data['version']) || empty($data['download_url'])) {
            return false;
        }

        // 6 saat önbelleğe al
        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * WordPress Güncelleme Havuzuna (Transient) Yeni Sürümü Enjekte Eder
     */
    public function check_for_plugin_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_info = $this->get_remote_version_info();

        if ($remote_info && !empty($remote_info['version'])) {
            if (version_compare($this->current_version, $remote_info['version'], '<')) {
                $obj = new stdClass();
                $obj->slug        = $this->plugin_slug;
                $obj->plugin      = $this->plugin_basename;
                $obj->new_version = $remote_info['version'];
                $obj->url         = $remote_info['homepage'] ?? 'https://misteknoloji360.com.tr/';
                $obj->package     = $remote_info['download_url'];
                $obj->icons       = $remote_info['icons'] ?? array();
                $obj->banners     = $remote_info['banners'] ?? array();
                $obj->tested      = $remote_info['tested'] ?? '6.7';
                $obj->requires    = $remote_info['requires'] ?? '5.8';
                $obj->requires_php= $remote_info['requires_php'] ?? '7.4';

                $transient->response[$this->plugin_basename] = $obj;
            }
        }

        return $transient;
    }

    /**
     * "Sürüm Detaylarını Görüntüle" Modal Penceresi İçeriğini Doldurur
     */
    public function plugin_popup_information($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $remote_info = $this->get_remote_version_info();

        if (!$remote_info) {
            return $result;
        }

        $info = new stdClass();
        $info->name           = $remote_info['name'] ?? 'AI Content & SEO Assistant';
        $info->slug           = $this->plugin_slug;
        $info->version        = $remote_info['version'];
        $info->author         = $remote_info['author'] ?? '<a href="https://misteknoloji360.com.tr/">Serkan AKKAYA</a>';
        $info->author_profile = 'https://misteknoloji360.com.tr/';
        $info->homepage       = $remote_info['homepage'] ?? 'https://misteknoloji360.com.tr/';
        $info->requires       = $remote_info['requires'] ?? '5.8';
        $info->tested         = $remote_info['tested'] ?? '6.7';
        $info->requires_php   = $remote_info['requires_php'] ?? '7.4';
        $info->last_updated   = $remote_info['last_updated'] ?? date('Y-m-d');
        $info->download_link  = $remote_info['download_url'];
        $info->sections       = array(
            'description' => $remote_info['sections']['description'] ?? 'Yapay zeka destekli içerik ve SEO motoru.',
            'changelog'   => $remote_info['sections']['changelog'] ?? 'Güncelleme notları.',
        );

        return $info;
    }

    /**
     * Zip Arşivinden Çıkarılan Klasör Adının Düzgün Olmasını Sağlar
     */
    public function fix_source_directory_name($source, $remote_source, $upgrader, $hook_extra = array()) {
        global $wp_filesystem;

        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            $correct_source = trailingslashit($remote_source) . $this->plugin_slug . '/';

            if ($source !== $correct_source) {
                $wp_filesystem->move($source, $correct_source);
                return $correct_source;
            }
        }

        return $source;
    }

    /**
     * Güncelleme Tamamlandığında Önbelleği Temizle
     */
    public function clear_update_cache($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient('ai_seo_remote_update_info');
            delete_site_transient('update_plugins');
        }
    }
}
