<?php
/**
 * WordPress Otomatik Pilot (Cron ile Günlük Makale Üretici)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Cron_Autopilot {

    private $options;
    private $ai_client;

    public function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->ai_client = new AI_SEO_Client();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Özel cron aralıkları (örneğin 2 günde bir)
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));

        // Cron tetikleyici kancası
        add_action('ai_seo_daily_autopilot_hook', array($this, 'execute_autopilot_generation'));
    }

    /**
     * Özel Cron Aralıkları Tanımla
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['every_2_days'] = array(
            'interval' => 172800, // 2 gün (saniye)
            'display'  => __('2 Günde Bir', 'ai-content-seo-assistant'),
        );
        $schedules['every_3_days'] = array(
            'interval' => 259200, // 3 gün (saniye)
            'display'  => __('3 Günde Bir', 'ai-content-seo-assistant'),
        );
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 gün
            'display'  => __('Haftada Bir', 'ai-content-seo-assistant'),
        );
        return $schedules;
    }

    /**
     * Cron Zamanlayıcısını Güncelle (Ayarlar kaydedildiğinde çağrılır)
     */
    public static function reschedule_cron($options) {
        $hook = 'ai_seo_daily_autopilot_hook';
        wp_clear_scheduled_hook($hook);

        if (!empty($options['autopilot_enabled'])) {
            $recurrence = !empty($options['autopilot_frequency']) ? $options['autopilot_frequency'] : 'daily';
            
            // Başlangıç zamanı (örneğin seçilen saatte başlat)
            $time_str = !empty($options['autopilot_time']) ? $options['autopilot_time'] : '09:00';
            $target_timestamp = strtotime('today ' . $time_str);
            
            // Eğer saat bugün geçtiyse yarına planla
            if ($target_timestamp < time()) {
                $target_timestamp = strtotime('tomorrow ' . $time_str);
            }

            wp_schedule_event($target_timestamp, $recurrence, $hook);
        }
    }

    /**
     * Otomatik Makale Üretim Süreci
     *
     * @param bool $is_manual Elle tetiklendi mi?
     * @return array Sonuç raporu
     */
    public function execute_autopilot_generation($is_manual = false) {
        // Lisans Kontrolü
        if (class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            $this->log_autopilot_result(__('Otomatik pilot çalıştırılamadı: Eklenti lisansı etkin değil (misteknoloji360.com.tr).', 'ai-content-seo-assistant'), 'error');
            return array('success' => false, 'message' => __('Eklenti lisansı etkin değil. Lütfen lisans anahtarınızı etkinleştirin.', 'ai-content-seo-assistant'));
        }

        $this->options = get_option('ai_seo_assistant_options', array());

        if (!$is_manual && empty($this->options['autopilot_enabled'])) {
            return array('success' => false, 'message' => __('Otomatik pilot devre dışı.', 'ai-content-seo-assistant'));
        }

        // 1. Konu Havuzunu Oku
        $topic_raw = $this->options['autopilot_topics'] ?? '';
        $topic_lines = array_filter(array_map('trim', explode("\n", $topic_raw)));
        $topic_lines = array_values($topic_lines); // İndeksleri sıfırla

        $current_topic = '';
        if (!empty($topic_lines)) {
            // İlk konuyu al
            $current_topic = array_shift($topic_lines);
            // Kalan konuları kaydet
            $this->options['autopilot_topics'] = implode("\n", $topic_lines);
            update_option('ai_seo_assistant_options', $this->options);
        } else {
            // Eğer havuz bittiyse AI'dan anlık konu iste
            $site_name = get_bloginfo('name') ?: 'Ratemo Mobilya';
            $suggest_prompt = "Bir '{$site_name}' blog sitesi için ilgi çekici, Google aramalarında öne çıkacak, özgün, merak uyandıran 1 adet Türkçe blog makalesi başlığı yaz. Sadece başlığı döndür, tırnak veya ek açıklama yazma.";
            $suggest_res = $this->ai_client->generate($suggest_prompt, '', array('provider' => $this->options['autopilot_provider'] ?? 'gemini'));
            if ($suggest_res['success'] && !empty(trim($suggest_res['content']))) {
                $current_topic = trim($suggest_res['content'], "\"'\n\r ");
            } else {
                $fallback_topics = array(
                    'Modern Ev Dekorasyonunda Ahşap Mobilya Seçimi ve Bakım Rehberi',
                    'Küçük Evler ve Salonlar İçin Fonksiyonel Yer Sofrası ve Mobilya Çözümleri',
                    '2026 Mobilya Trendleri: Konfor ve Şıklığı Bir Araya Getiren Tasarımlar',
                    'Kaliteli Ahşap Mobilya Nasıl Anlaşılır? Alışveriş Yaparken Dikkat Edilmesi Gerekenler',
                    'Geleneksel ve Modern Yaşamda Yer Sofrası Kültürü ve Kullanım Avantajları',
                    'Dar Mutfaklar İçin Pratik Alan Kazandıran Katlanır Masa Çözümleri',
                    'Doğal Ahşap Mobilyaların Ev Sağlığına ve Enerjisine Faydaları'
                );
                $current_topic = $fallback_topics[array_rand($fallback_topics)];
            }
        }

        if (empty($current_topic)) {
            $current_topic = '2026 Modern Mobilya ve Ev Dekorasyonu Trendleri';
        }

        $provider = $this->options['autopilot_provider'] ?? 'gemini';
        $language = $this->options['default_language'] ?? 'tr';
        $tone = $this->options['default_tone'] ?? 'professional';
        $post_status = $this->options['autopilot_status'] ?? 'publish'; // 'publish' veya 'draft'
        $category_id = intval($this->options['autopilot_category'] ?? 0);

        // 2. Makale İçeriğini Üret
        $system_prompt = "Sen uzman bir SEO içerik yazarı ve Türkçe blog editörüsün. WordPress için yüksek kaliteli, okunabilirliği yüksek, SEO uyumlu ve zengin içerikler üretiyorsun. Yanıtını doğrudan HTML biçiminde (<h2>, <h3>, <p>, <ul>, <li>, <strong> etiketleriyle) oluştur. Kod blokları veya markdown backtick (```html) ekleme, doğrudan saf HTML metni döndür. Dil: " . $language . ".";
        
        $article_prompt = "Konu: '{$current_topic}'. Yazım Tonu: '{$tone}'. Bu konu hakkında baştan sona kapsamlı, detaylı, alt başlıklara ayrılmış (H2, H3), listeler ve ipuçları içeren, SEO odaklı tam bir blog makalesi yaz.";

        $article_res = $this->ai_client->generate($article_prompt, $system_prompt, array(
            'provider'   => $provider,
            'max_tokens' => 3000,
        ));

        if (!$article_res['success']) {
            $this->log_autopilot_result(sprintf(__('Makale üretilemedi (%s): %s', 'ai-content-seo-assistant'), $current_topic, $article_res['error']), 'error');
            return array('success' => false, 'message' => $article_res['error']);
        }

        $content_html = $article_res['content'];

        // 3. SEO Meta Başlığı ve Açıklamasını Üret
        $meta_title = $current_topic;
        $title_res = $this->ai_client->generate("Konu: '{$current_topic}'. Google arama sonuçlarında yüksek tıklama oranı alacak en fazla 55-60 karakterlik tek bir SEO başlığı yaz. Sadece başlığı döndür.", '', array('provider' => $provider, 'max_tokens' => 100));
        if ($title_res['success']) {
            $meta_title = trim($title_res['content'], "\"'\n\r ");
        }

        $meta_desc = '';
        $desc_res = $this->ai_client->generate("Konu: '{$current_topic}'. Google arama sonuçları için 130-155 karakterlik merak uyandıran bir meta açıklaması yaz. Sadece açıklamayı döndür.", '', array('provider' => $provider, 'max_tokens' => 150));
        if ($desc_res['success']) {
            $meta_desc = trim($desc_res['content'], "\"'\n\r ");
        }

        // 4. WordPress Yazısı Olarak Kaydet
        $post_data = array(
            'post_title'    => sanitize_text_field(wp_strip_all_tags($meta_title)),
            'post_content'  => wp_kses_post($content_html),
            'post_status'   => in_array($post_status, array('publish', 'draft', 'pending'), true) ? $post_status : 'publish',
            'post_type'     => 'post',
            'post_author'   => get_current_user_id() ?: 1,
        );

        if ($category_id > 0) {
            $post_data['post_category'] = array($category_id);
        }

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id) || !$post_id) {
            $err = is_wp_error($post_id) ? $post_id->get_error_message() : __('Yazı veritabanına eklenemedi.', 'ai-content-seo-assistant');
            $this->log_autopilot_result(sprintf(__('WordPress kayıt hatası: %s', 'ai-content-seo-assistant'), esc_html($err)), 'error');
            return array('success' => false, 'message' => $err);
        }

        // 5. SEO Meta Verilerini Kaydet
        update_post_meta($post_id, '_ai_seo_meta_title', sanitize_text_field($meta_title));
        update_post_meta($post_id, '_ai_seo_meta_desc', sanitize_textarea_field($meta_desc));
        update_post_meta($post_id, '_ai_seo_focus_keyword', sanitize_text_field($current_topic));

        $log_msg = sprintf(
            __('✓ "%s" başlıklı makale otomatik üretildi ve kaydedildi (ID: %d, Durum: %s).', 'ai-content-seo-assistant'),
            esc_html($meta_title),
            $post_id,
            strtoupper($post_status)
        );

        $this->log_autopilot_result($log_msg, 'success', $post_id);

        return array(
            'success' => true,
            'post_id' => $post_id,
            'title'   => $meta_title,
            'status'  => $post_status,
            'url'     => get_edit_post_link($post_id, 'raw'),
            'message' => $log_msg,
        );
    }

    /**
     * Otomatik Pilot Geçmiş Günlüğü
     */
    private function log_autopilot_result($message, $type = 'success', $post_id = 0) {
        $logs = get_option('ai_seo_autopilot_logs', array());
        if (!is_array($logs)) {
            $logs = array();
        }

        array_unshift($logs, array(
            'time'    => current_time('mysql'),
            'message' => $message,
            'type'    => $type,
            'post_id' => $post_id,
        ));

        // Son 50 kaydı tut
        $logs = array_slice($logs, 0, 50);
        update_option('ai_seo_autopilot_logs', $logs);
    }
}
