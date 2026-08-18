<?php
/**
 * Eklenti Ana Çekirdek Sınıfı (Core Singleton)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Core {

    private static $instance = null;
    private $options;
    private $seo_engine;
    private $admin_settings;
    private $editor_metabox;
    private $ajax_handler;
    private $autopilot;
    private $updater;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->init_components();
        $this->init_hooks();
    }

    private function init_components() {
        $this->seo_engine     = new AI_SEO_Engine();
        $this->autopilot      = new AI_SEO_Cron_Autopilot();
        $this->admin_settings = new AI_SEO_Admin_Settings();
        $this->editor_metabox = new AI_SEO_Editor_Metabox();
        $this->ajax_handler   = new AI_SEO_Ajax_Handler();
        $this->updater        = new AI_SEO_Plugin_Updater(AI_SEO_PLUGIN_DIR . 'ai-content-seo-assistant.php', AI_SEO_VERSION);
    }

    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . AI_SEO_PLUGIN_BASENAME, array($this, 'add_action_links'));
        add_action('init', array($this, 'handle_public_ping'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Canlı Lisans ve Sinyal Sorgulama Uç Noktası (Public Heartbeat Ping)
     */
    public function handle_public_ping() {
        if (isset($_GET['ai_seo_ping']) && $_GET['ai_seo_ping'] == '1') {
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            $license_info = AI_SEO_License_Manager::get_license_info();
            echo wp_json_encode(array(
                'success'      => true,
                'plugin'       => 'ai-content-seo-assistant',
                'name'         => 'AI Content & SEO Assistant',
                'version'      => AI_SEO_VERSION,
                'is_licensed'  => $license_info['is_active'],
                'license_key'  => $license_info['key'],
                'domain'       => AI_SEO_License_Manager::get_current_domain(),
                'wp_version'   => get_bloginfo('version'),
                'php_version'  => phpversion(),
                'server_time'  => current_time('mysql'),
                'developer'    => 'Serkan AKKAYA (misteknoloji360.com.tr)',
            ));
            exit;
        }
    }

    /**
     * WP REST API Uç Noktası
     */
    public function register_rest_routes() {
        register_rest_route('ai-seo/v1', '/ping', array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => function() {
                $license_info = AI_SEO_License_Manager::get_license_info();
                return array(
                    'success'      => true,
                    'plugin'       => 'ai-content-seo-assistant',
                    'name'         => 'AI Content & SEO Assistant',
                    'version'      => AI_SEO_VERSION,
                    'is_licensed'  => $license_info['is_active'],
                    'license_key'  => $license_info['key'],
                    'domain'       => AI_SEO_License_Manager::get_current_domain(),
                    'wp_version'   => get_bloginfo('version'),
                    'php_version'  => phpversion(),
                    'server_time'  => current_time('mysql'),
                    'developer'    => 'Serkan AKKAYA (misteknoloji360.com.tr)',
                );
            },
        ));
    }

    /**
     * Eklentiler Sayfasında "Ayarlar" Linki
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-content-seo-assistant') . '">' . __('Ayarlar', 'ai-content-seo-assistant') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Admin CSS ve JS Dosyalarını Yükle
     */
    public function enqueue_admin_assets($hook) {
        // 1. Ayarlar Sayfası Asset'leri
        $is_ai_page = (strpos($hook, 'ai-content-seo-assistant') !== false) || 
                      (isset($_GET['page']) && strpos(sanitize_text_field($_GET['page']), 'ai-content-seo-assistant') !== false);

        if ($is_ai_page) {
            wp_enqueue_style(
                'ai-seo-admin-style',
                AI_SEO_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                AI_SEO_VERSION
            );

            wp_enqueue_script(
                'ai-seo-admin-settings',
                AI_SEO_PLUGIN_URL . 'assets/js/admin-settings.js',
                array('jquery'),
                AI_SEO_VERSION,
                true
            );

            wp_localize_script('ai-seo-admin-settings', 'aiSeoSettings', array(
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ai_seo_settings_nonce'),
                'strings'  => array(
                    'testing' => __('Bağlantı test ediliyor...', 'ai-content-seo-assistant'),
                    'error'   => __('Bir hata oluştu.', 'ai-content-seo-assistant'),
                )
            ));
        }

        // 2. Post / Page Düzenleme Ekranı Asset'leri
        $screen = get_current_screen();
        $post_types = $this->options['post_types'] ?? array('post', 'page');

        if ($screen && in_array($screen->post_type, $post_types) && in_array($screen->base, array('post', 'page'))) {
            wp_enqueue_style(
                'ai-seo-editor-style',
                AI_SEO_PLUGIN_URL . 'assets/css/editor-panel.css',
                array('dashicons'),
                AI_SEO_VERSION
            );

            wp_enqueue_script(
                'ai-seo-editor-js',
                AI_SEO_PLUGIN_URL . 'assets/js/editor-assistant.js',
                array('jquery'),
                AI_SEO_VERSION,
                true
            );

            wp_localize_script('ai-seo-editor-js', 'aiSeoEditor', array(
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ai_seo_editor_nonce'),
                'siteName' => get_bloginfo('name'),
                'strings'  => array(
                    'generating' => __('Yapay zeka yanıt oluşturuyor...', 'ai-content-seo-assistant'),
                    'inserted'   => __('İçerik editöre eklendi!', 'ai-content-seo-assistant'),
                    'copied'     => __('Panoya kopyalandı!', 'ai-content-seo-assistant'),
                    'error'      => __('Hata oluştu:', 'ai-content-seo-assistant'),
                )
            ));
        }
    }
}
