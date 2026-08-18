<?php
/**
 * Plugin Name:       AI Content & SEO Assistant
 * Plugin URI:        https://misteknoloji360.com.tr/
 * Description:       Yapay zeka destekli otomatik içerik üretimi, zamanlanmış günlük makale yayınlayıcı (Cron), canlı SERP önizlemeli SEO meta etiketleri, Schema.org JSON-LD yapısal veri ve Lisanslama motoru. Groq, Gemini, DeepSeek, OpenRouter, Claude ve OpenAI destekler.
 * Version:           1.1.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Serkan AKKAYA
 * Author URI:        https://misteknoloji360.com.tr/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-content-seo-assistant
 * Domain Path:       /languages
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Sabit tanımlamaları
define('AI_SEO_VERSION', '1.1.4');
define('AI_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_SEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Dahili sınıfları yükle
require_once AI_SEO_PLUGIN_DIR . 'includes/class-license-manager.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-ai-client.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-seo-engine.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-cron-autopilot.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-plugin-updater.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-editor-metabox.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once AI_SEO_PLUGIN_DIR . 'includes/class-ai-seo-core.php';

/**
 * Eklenti aktivasyon kancası
 */
function ai_seo_assistant_activate() {
    // Varsayılan ayarları kaydet
    $default_settings = array(
        'default_provider'      => 'groq',
        'groq_key'              => '',
        'groq_model'            => 'llama-3.1-8b-instant',
        'openai_key'            => '',
        'openai_model'          => 'gpt-4o-mini',
        'anthropic_key'         => '',
        'anthropic_model'       => 'claude-3-5-haiku-20241022',
        'gemini_key'            => '',
        'gemini_model'          => 'gemini-2.5-flash',
        'deepseek_key'          => '',
        'deepseek_model'        => 'deepseek-chat',
        'openrouter_key'        => '',
        'openrouter_model'      => 'google/gemini-2.0-flash-exp:free',
        'custom_base_url'       => '',
        'custom_key'            => '',
        'custom_model'          => '',
        'temperature'           => 0.7,
        'max_tokens'            => 2000,
        'default_tone'          => 'professional',
        'default_language'      => 'tr',
        'enable_schema'         => 1,
        'enable_opengraph'      => 1,
        'enable_twitter'        => 1,
        'post_types'            => array('post', 'page'),
        'autopilot_enabled'     => 0,
        'autopilot_frequency'   => 'daily',
        'autopilot_time'        => '09:00',
        'autopilot_provider'    => 'gemini',
        'autopilot_status'      => 'publish',
        'autopilot_category'    => 0,
        'autopilot_topics'      => "Yer Sofrası Seçerken Dikkat Edilmesi Gerekenler ve Ahşap Modeller\nKüçük Odalar İçin Katlanır Yemek Masası Avantajları\nGeleneksel Yer Sofrası Kültürü ve Modern Ev Dekorasyonundaki Yeri\nAhşap Mobilya Temizliği ve Parlatma Yöntemleri\nBalkon ve Bahçe İçin Katlanabilir Ahşap Masa Modelleri\nDar Mutfaklar İçin Pratik Alan Kazandıran Masa Çözümleri\nDoğal Ahşap Mobilyaların Ev Sağlığına ve Enerjisine Faydaları\n6 Kişilik Katlanır Yer Sofrası Modelleri ve Kullanım İpuçları\nRustik ve Ahşap Dekorasyonda En Çok Tercih Edilen Renk Kombinasyonları\nEvde Aile ve Misafirlerle Yer Sofrasında Yemek Yemenin Keyfi",
        'autopilot_auto_suggest'=> 1,
    );

    if (!get_option('ai_seo_assistant_options')) {
        update_option('ai_seo_assistant_options', $default_settings);
    }
}
register_activation_hook(__FILE__, 'ai_seo_assistant_activate');

/**
 * Eklenti deaktivasyon kancası
 */
function ai_seo_assistant_deactivate() {
    // Zamanlanmış cron görevini temizle
    wp_clear_scheduled_hook('ai_seo_daily_autopilot_hook');
    delete_transient('ai_seo_api_test_result');
}
register_deactivation_hook(__FILE__, 'ai_seo_assistant_deactivate');

/**
 * Eklenti çekirdeğini başlat
 */
function ai_seo_assistant_init() {
    AI_SEO_Core::get_instance();
}
add_action('plugins_loaded', 'ai_seo_assistant_init');
