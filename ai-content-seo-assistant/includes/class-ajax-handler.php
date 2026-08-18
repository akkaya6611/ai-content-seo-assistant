<?php
/**
 * Güvenli AJAX İstek Yöneticisi
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Ajax_Handler {

    private $ai_client;
    private $options;

    public function __construct() {
        $this->ai_client = new AI_SEO_Client();
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_ai_seo_generate_content', array($this, 'handle_generate_content'));
        add_action('wp_ajax_ai_seo_rephrase_text', array($this, 'handle_rephrase_text'));
        add_action('wp_ajax_ai_seo_generate_meta', array($this, 'handle_generate_meta'));
        add_action('wp_ajax_ai_seo_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_ai_seo_trigger_autopilot_now', array($this, 'handle_trigger_autopilot_now'));
        add_action('wp_ajax_ai_seo_force_check_updates', array($this, 'handle_force_check_updates'));
        add_action('wp_ajax_ai_seo_activate_license', array($this, 'handle_activate_license'));
        add_action('wp_ajax_ai_seo_deactivate_license', array($this, 'handle_deactivate_license'));
    }

    /**
     * Otomatik Pilotu Şimdi Manuel Olarak Çalıştır (Test Et)
     */
    public function handle_trigger_autopilot_now() {
        check_ajax_referer('ai_seo_settings_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        if (class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            wp_send_json_error(array('message' => __('🔒 Eklenti Lisansınız Etkin Değil! Lütfen Ayarlar > Lisans sekmesinden lisans anahtarınızı etkinleştirin (misteknoloji360.com.tr).', 'ai-content-seo-assistant')));
        }

        $autopilot = new AI_SEO_Cron_Autopilot();
        $result = $autopilot->execute_autopilot_generation(true);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AI İçerik Üretimi (Makale, Taslak, Başlıklar, SSS vb.)
     */
    public function handle_generate_content() {
        check_ajax_referer('ai_seo_editor_nonce', 'security');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        if (class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            wp_send_json_error(array('message' => __('🔒 Eklenti Lisansınız Etkin Değil! Lütfen Ayarlar > Lisans sekmesinden lisans anahtarınızı etkinleştirin (misteknoloji360.com.tr).', 'ai-content-seo-assistant')));
        }

        $type = sanitize_text_field($_POST['gen_type'] ?? 'article');
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $language = $this->options['default_language'] ?? 'tr';

        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Lütfen bir konu veya başlık girin.', 'ai-content-seo-assistant')));
        }

        $system_prompt = "Sen Google SEO, içerik pazarlaması ve profesyonel Türkçe blog yazımı konusunda uzman bir baş editörsün.\n\nKESİN KURALLAR:\n1. KESİNLİKLE düşünce süreci (thinking process, chain of thought, analyze input vb.) YAZMA.\n2. KESİNLİKLE İngilizce kelime, parantez içi İngilizce çeviri veya açıklama EKLEME.\n3. KESİNLİKLE 'Here is...', 'Sure', 'Tabii ki' gibi giriş cümleleri YAZMA.\n4. Yanıtını DOĞRUDAN saf HTML biçiminde (<h2>, <h3>, <p>, <ul>, <li>, <strong>, <blockquote>) oluştur.\n5. Kod blokları veya markdown backtick (```html) ASLA ekleme.\n6. Girişte okuyucuyu kancalayan (Hook) merak uyandırıcı bir giriş yap.\n7. Alt başlıklar (H2, H3), maddeli listeler ve SSS (FAQ) bölümleriyle zenginleştir.\n8. Dil: %100 kusursuz, akıcı, zengin ve profesyonel Türkçe (" . $language . ").";

        $user_prompt = "";
        switch ($type) {
            case 'titles':
                $system_prompt = "Sen Google arama motoru (SERP) ve yüksek tıklama oranı (CTR) uzmanısın. KESİNLİKLE düşünce süreci veya İngilizce çeviri yazma. Sadece saf Türkçe başlıkları döndür.";
                $user_prompt = "Konu: '{$topic}'. Odak Anahtar Kelimeler: '{$keywords}'.\n\nBu konu hakkında Google aramalarında en çok tıklanacak 5 adet özgün ve dikkat çekici Türkçe blog başlığı önerisi yaz.\n\nKurallar:\n- Uzunlukları 45-65 karakter arasında olsun.\n- Liste halinde (1., 2., 3., 4., 5.) olarak sadece başlıkları yaz.";
                break;

            case 'outline':
                $user_prompt = "Konu: '{$topic}'. Anahtar Kelimeler: '{$keywords}'. Bu konu için H2 ve H3 başlıklarını içeren, mantıksal akışa sahip, eksiksiz bir makale taslağı (outline) ve planı hazırla.";
                break;

            case 'intro':
                $user_prompt = "Konu: '{$topic}'. Anahtar Kelimeler: '{$keywords}'. Yazım Tonu: '{$tone}'. Okuyucunun dikkatini ilk 5 saniyede çekecek, problemi ve sunulacak çözümü özetleyen etkileyici 2-3 paragraflık bir giriş bölümü (kanca) yaz.";
                break;

            case 'conclusion':
                $user_prompt = "Konu: '{$topic}'. Anahtar Kelimeler: '{$keywords}'. Yazım Tonu: '{$tone}'. Makaleyi güçlü bir şekilde özetleyen ve okuyucuyu yorum yapmaya veya harekete geçmeye yönlendiren (Call to Action) bir sonuç bölümü yaz.";
                break;

            case 'faq':
                $user_prompt = "Konu: '{$topic}'. Bu konu hakkında kullanıcıların Google'da en çok arattığı 4-5 sıkça sorulan soruyu (SSS / FAQ) belirle ve her birine net, anlaşılır ve güven verici yanıtlar yaz. H3 soru başlıkları ve paragraflar kullan.";
                break;

            case 'article':
            default:
                $user_prompt = "Konu Başlığı: '{$topic}'.\nOdak Anahtar Kelimeler: '{$keywords}'.\nYazım Tonu: '{$tone}'.\n\nBu konu hakkında baştan sona kapsamlı, detaylı, alt başlıklara ayrılmış (H2, H3), maddeli listeler ve kullanım ipuçları içeren, SEO odaklı tam bir blog makalesi yaz.";
                break;
        }

        $opts = array();
        if (!empty($provider)) {
            $opts['provider'] = $provider;
        }

        $response = $this->ai_client->generate($user_prompt, $system_prompt, $opts);

        if ($response['success']) {
            $content = $response['content'];
            if ($type === 'titles') {
                $lines = explode("\n", $content);
                $html_output = '<div class="ai-suggested-titles-list" style="display:flex; flex-direction:column; gap:10px; margin-top:5px;">';
                $count = 0;
                foreach ($lines as $line) {
                    $clean = trim(wp_strip_all_tags($line), "\"'\n\r#* ");
                    $clean = preg_replace('/^(başlık|seo başlığı|title|öneri)\s*:\s*/iu', '', $clean);
                    $clean = preg_replace('/^(\d+[\.\-\)]\s*)/u', '', $clean);
                    $clean = trim($clean, "\"'\n\r#* ");
                    if (mb_strlen($clean, 'UTF-8') >= 6) {
                        $count++;
                        $html_output .= '<div class="ai-title-suggestion-card" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 14px; display:flex; align-items:center; justify-content:space-between; gap:10px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">';
                        $html_output .= '<div style="flex:1;"><span style="display:inline-block; width:20px; height:20px; line-height:20px; text-align:center; background:#f1f5f9; color:#475569; border-radius:50%; font-size:11px; font-weight:bold; margin-right:8px;">' . $count . '</span><strong class="ai-suggested-title-text" style="color:#0f172a; font-size:13.5px;">' . esc_html($clean) . '</strong></div>';
                        $html_output .= '<button type="button" class="button button-small ai-btn-apply-title" data-title="' . esc_attr($clean) . '" style="background:#2563eb; color:#ffffff; border:none; font-weight:600; white-space:nowrap; padding:4px 12px; height:auto; cursor:pointer;">🎯 Bu Başlığı Seç</button>';
                        $html_output .= '</div>';
                    }
                }
                $html_output .= '</div>';
                $content = $html_output;
            }
            wp_send_json_success(array('content' => $content));
        } else {
            wp_send_json_error(array('message' => sanitize_text_field($response['error'])));
        }
    }

    /**
     * Metin İyileştirme (Paraphrase, Expand, Summarize, Grammar)
     */
    public function handle_rephrase_text() {
        check_ajax_referer('ai_seo_editor_nonce', 'security');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        if (class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            wp_send_json_error(array('message' => __('🔒 Eklenti Lisansınız Etkin Değil! Lütfen Ayarlar > Lisans sekmesinden lisans anahtarınızı etkinleştirin (misteknoloji360.com.tr).', 'ai-content-seo-assistant')));
        }

        $text = wp_kses_post($_POST['text'] ?? '');
        $action = sanitize_text_field($_POST['rephrase_action'] ?? 'rephrase');
        $provider = sanitize_text_field($_POST['provider'] ?? '');

        if (empty(trim($text))) {
            wp_send_json_error(array('message' => __('Lütfen iyileştirilecek bir metin girin.', 'ai-content-seo-assistant')));
        }

        $system_prompt = "Sen uzman bir profesyonel metin editörüsün. Sana verilen metni istenen kurala göre dönüştür ve doğrudan nihai metni döndür. Açıklama veya selamlama ekleme.";

        $user_prompt = "";
        switch ($action) {
            case 'expand':
                $user_prompt = "Aşağıdaki metni anlamını koruyarak örneklerle, detaylarla ve açıklayıcı cümlelerle genişlet:\n\n" . $text;
                break;
            case 'summarize':
                $user_prompt = "Aşağıdaki metnin ana fikrini ve en kritik noktalarını kısa, öz bir paragraf halinde özetle:\n\n" . $text;
                break;
            case 'grammar':
                $user_prompt = "Aşağıdaki metindeki tüm yazım, imla, noktalama ve anlatım bozukluklarını düzelt:\n\n" . $text;
                break;
            case 'rephrase':
            default:
                $user_prompt = "Aşağıdaki metni anlamını ve ana mesajını bozmadan daha akıcı, profesyonel ve özgün kelimelerle yeniden yaz:\n\n" . $text;
                break;
        }

        $opts = array();
        if (!empty($provider)) {
            $opts['provider'] = $provider;
        }

        $response = $this->ai_client->generate($user_prompt, $system_prompt, $opts);

        if ($response['success']) {
            wp_send_json_success(array('content' => wp_kses_post($response['content'])));
        } else {
            wp_send_json_error(array('message' => sanitize_text_field($response['error'])));
        }
    }

    /**
     * Otomatik SEO Meta Başlık ve Açıklama Üretimi
     */
    public function handle_generate_meta() {
        check_ajax_referer('ai_seo_editor_nonce', 'security');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        if (class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            wp_send_json_error(array('message' => __('🔒 Eklenti Lisansınız Etkin Değil! Lütfen Ayarlar > Lisans sekmesinden lisans anahtarınızı etkinleştirin (misteknoloji360.com.tr).', 'ai-content-seo-assistant')));
        }

        $field = sanitize_text_field($_POST['field'] ?? 'title'); // 'title' veya 'desc'
        $post_title = sanitize_text_field($_POST['title'] ?? '');
        $post_content = wp_strip_all_tags($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $provider = sanitize_text_field($_POST['provider'] ?? '');

        $system_prompt = "Sen bir Google SERP ve Tıklama Oranı (CTR) uzmanısın. SADECE tek bir nihai metin döndür. Düşünce süreci (thinking process), İngilizce kelime/çeviri, tırnak işareti, 'Başlık:', 'Açıklama:' gibi etiketler veya konuşma cümleleri KESİNLİKLE ekleme.";

        if ($field === 'title') {
            $user_prompt = "Aşağıdaki içerik için Google arama sonuçlarında (SERP) en yüksek tıklamayı alacak profesyonel ve doğal tek bir SEO Meta Başlığı yaz.\n\nİçerik Başlığı: {$post_title}\nOdak Anahtar Kelime: {$keyword}\nÖzet: " . substr($post_content, 0, 300) . "\n\nKurallar:\n- Uzunluk kesinlikle 45 ile 60 karakter arasında olmalı (Google'da kesilmemeli).\n- Odak anahtar kelimeyi başlığın içine doğal şekilde yerleştir.\n- Sadece saf başlık metnini döndür.";
        } else {
            $user_prompt = "Aşağıdaki içerik için Google arama sonuçlarında gösterilecek yüksek CTR odaklı tek bir Meta Açıklaması (Meta Description) yaz.\n\nİçerik Başlığı: {$post_title}\nOdak Anahtar Kelime: {$keyword}\nÖzet: " . substr($post_content, 0, 500) . "\n\nKurallar:\n- Uzunluk kesinlikle 130 ile 155 karakter arasında olmalı.\n- Okuyucuyu tıklamaya teşvik eden (CTA) güçlü bir eylem cümlesi içermeli.\n- Sadece saf açıklama metnini döndür.";
        }

        $opts = array('max_tokens' => 200, 'temperature' => 0.5);
        if (!empty($provider)) {
            $opts['provider'] = $provider;
        }

        $response = $this->ai_client->generate($user_prompt, $system_prompt, $opts);

        if ($response['success']) {
            $clean_result = trim(wp_strip_all_tags($response['content']), "\"'\n\r#* ");
            $clean_result = preg_replace('/^(başlık|seo başlığı|title|açıklama|meta açıklama|description)\s*:\s*/iu', '', $clean_result);
            $clean_result = trim($clean_result, "\"'\n\r#* ");
            wp_send_json_success(array('result' => sanitize_text_field($clean_result)));
        } else {
            wp_send_json_error(array('message' => sanitize_text_field($response['error'])));
        }
    }

    /**
     * Ayarlar Sayfası: API Bağlantısını Test Etme
     */
    public function handle_test_connection() {
        check_ajax_referer('ai_seo_settings_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        $provider = sanitize_text_field($_POST['provider'] ?? 'openrouter');
        $key = sanitize_text_field(trim($_POST['key'] ?? ''));
        $model = sanitize_text_field(trim($_POST['model'] ?? ''));
        $base_url = esc_url_raw(trim($_POST['base_url'] ?? ''));

        $saved_opts = get_option('ai_seo_assistant_options', array());
        $actual_key = $key ?: ($saved_opts[$provider . '_key'] ?? '');

        if (empty($actual_key) && $provider !== 'custom') {
            wp_send_json_error(array('message' => __('Lütfen test etmeden önce API anahtarını girin.', 'ai-content-seo-assistant')));
        }

        $test_opts = array(
            'provider'   => $provider,
            'model'      => $model,
            'key'        => $actual_key,
            'base_url'   => $base_url ?: ($saved_opts['custom_base_url'] ?? ''),
            'max_tokens' => 30,
            'is_test'    => true,
        );

        $client = new AI_SEO_Client();
        $start_time = microtime(true);
        $res = $client->generate("Say 'API connected successfully.' in 4 words.", "Answer concisely.", $test_opts);
        $duration = round((microtime(true) - $start_time) * 1000);

        // Kullanıcının girdiği API anahtarı ve modeli hemen veritabanına kalıcı kaydet (asla kaybolmasın)
        if (!empty($key)) {
            $saved_opts[$provider . '_key'] = $key;
            if (!empty($model)) {
                $saved_opts[$provider . '_model'] = $model;
            }
            if ($provider === 'custom') {
                $saved_opts['custom_base_url'] = $base_url;
                $saved_opts['custom_key'] = $key;
                $saved_opts['custom_model'] = $model;
            }
            update_option('ai_seo_assistant_options', $saved_opts);
        }

        if ($res['success']) {
            wp_send_json_success(array(
                'message' => sprintf(__('✓ Başarılı! Bağlantı kuruldu (%d ms). Yanıt: "%s"', 'ai-content-seo-assistant'), $duration, esc_html($res['content'])),
            ));
        } else {
            wp_send_json_error(array(
                'message' => $res['error'],
            ));
        }
    }

    /**
     * Güncellemeleri Manuel Olarak Denetle (Force Check)
     */
    public function handle_force_check_updates() {
        check_ajax_referer('ai_seo_settings_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        delete_transient('ai_seo_remote_update_info');
        delete_site_transient('update_plugins');

        require_once AI_SEO_PLUGIN_DIR . 'includes/class-plugin-updater.php';
        $updater = new AI_SEO_Plugin_Updater(AI_SEO_PLUGIN_DIR . 'ai-content-seo-assistant.php', AI_SEO_VERSION);
        $remote_info = $updater->get_remote_version_info(true);

        if (!$remote_info) {
            wp_send_json_error(array('message' => __('Güncelleme sunucusuna bağlanılamadı veya geçerli sürüm bilgisi alınamadı.', 'ai-content-seo-assistant')));
        }

        $latest_version = $remote_info['version'] ?? '1.0.0';

        if (version_compare(AI_SEO_VERSION, $latest_version, '<')) {
            $transient = get_site_transient('update_plugins');
            if (!is_object($transient)) {
                $transient = new stdClass();
            }
            if (empty($transient->checked)) {
                $transient->checked = array();
            }
            $transient->checked[plugin_basename(AI_SEO_PLUGIN_DIR . 'ai-content-seo-assistant.php')] = AI_SEO_VERSION;
            $transient = $updater->check_for_plugin_update($transient);
            set_site_transient('update_plugins', $transient);

            wp_send_json_success(array(
                'has_update' => true,
                'message'    => sprintf(__('🎉 Yeni bir sürüm mevcut! (Mevcut: v%s &rarr; Yeni: v%s). WordPress Eklentiler sayfasından tek tıkla güncelleyebilirsiniz.', 'ai-content-seo-assistant'), AI_SEO_VERSION, $latest_version),
                'plugins_url'=> admin_url('plugins.php'),
            ));
        } else {
            wp_send_json_success(array(
                'has_update' => false,
                'message'    => sprintf(__('✓ Tebrikler! Eklentiniz güncel (v%s). Yeni bir güncelleme bulunmuyor.', 'ai-content-seo-assistant'), AI_SEO_VERSION),
            ));
        }
    }

    /**
     * Lisansı Etkinleştirme
     */
    public function handle_activate_license() {
        check_ajax_referer('ai_seo_settings_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        $license_key = sanitize_text_field(trim($_POST['license_key'] ?? ''));
        require_once AI_SEO_PLUGIN_DIR . 'includes/class-license-manager.php';
        $result = AI_SEO_License_Manager::activate($license_key);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Lisansı Kaldırma / Devre Dışı Bırakma
     */
    public function handle_deactivate_license() {
        check_ajax_referer('ai_seo_settings_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Yetkisiz işlem.', 'ai-content-seo-assistant')));
        }

        require_once AI_SEO_PLUGIN_DIR . 'includes/class-license-manager.php';
        $result = AI_SEO_License_Manager::deactivate();

        wp_send_json_success($result);
    }
}
