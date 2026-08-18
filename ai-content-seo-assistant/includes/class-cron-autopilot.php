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
            // Eğer havuz bittiyse AI'dan anlık sektör odaklı profesyonel konu iste
            $site_name = get_bloginfo('name') ?: 'Ratemo Mobilya';
            $suggest_prompt = "Sen Türkiye'nin önde gelen mobilya, dekorasyon ve ev yaşamı blog editörüsün. '{$site_name}' web sitesi için Google'da arama hacmi yüksek, kullanıcıların merak ettiği, doğal, profesyonel ve yüksek tıklama alacak 1 adet Türkçe blog makale başlığı yaz.\n\nKURALLAR:\n- Uzunluk 45-60 karakter olsun.\n- Sadece saf başlık metnini yaz.\n- Tırnak işareti, 'Başlık:', selamlama veya açıklama ASLA ekleme.";
            $suggest_res = $this->ai_client->generate($suggest_prompt, '', array('provider' => $this->options['autopilot_provider'] ?? 'gemini', 'max_tokens' => 100, 'temperature' => 0.7));
            if ($suggest_res['success'] && !empty(trim($suggest_res['content']))) {
                $current_topic = $this->clean_title_text($suggest_res['content']);
            } else {
                $fallback_topics = array(
                    'Ahşap Yer Sofrası Modelleri ve Doğru Seçim Rehberi',
                    'Küçük Evler İçin Katlanır Yemek Masası Avantajları ve Fiyatları',
                    '2026 Modern Mobilya ve Ev Dekorasyonu Trendleri',
                    'Kaliteli Ahşap Mobilya Nasıl Anlaşılır? Satın Alma Rehberi',
                    'Geleneksel Yer Sofrası Kültürü ve Modern Evlerdeki Yeri',
                    'Dar Mutfaklar İçin Pratik Alan Kazandıran Masa Çözümleri',
                    'Doğal Masif Ahşap Mobilyaların Ev Sağlığına ve Yaşama Faydaları'
                );
                $current_topic = $fallback_topics[array_rand($fallback_topics)];
            }
        }

        if (empty($current_topic)) {
            $current_topic = 'Ahşap Yer Sofrası Modelleri ve Doğru Seçim Rehberi';
        }

        $provider = $this->options['autopilot_provider'] ?? 'gemini';
        $language = $this->options['default_language'] ?? 'tr';
        $tone = $this->options['default_tone'] ?? 'professional';
        $post_status = $this->options['autopilot_status'] ?? 'publish'; // 'publish' veya 'draft'
        $category_id = intval($this->options['autopilot_category'] ?? 0);

        // 2. Makale İçeriğini Üret (Derinlemesine & Profesyonel SEO & Marka Odaklı)
        $site_name = get_bloginfo('name') ?: 'Ratemo Mobilya';

        $system_prompt = "Sen profesyonel bir SEO içerik uzmanı, e-ticaret içerik stratejisti ve marka editörüsün.\n\n"
            . "Görevin: Verilen konu başlığı, marka bilgileri ('{$site_name}') ve ürün detaylarına göre Google arama sonuçlarında güçlü performans gösterecek, kullanıcı odaklı, doğal ve güvenilir uzun format SEO makaleleri üretmek.\n\n"
            . "Ürettiğin her makale aşağıdaki kurallara KESİNLİKLE uymalıdır:\n\n"
            . "1. İÇERİK AMACI:\n"
            . "- Makale sadece bilgi vermemeli; markayı ('{$site_name}'), ürünleri ve satın alma kararını desteklemelidir.\n"
            . "- İçeriğin en az %60'ı marka ve sunduğu çözümlerle ilgili olmalı. Genel bilgiler sadece markayı desteklemek için kullanılmalı.\n"
            . "- Marka adı doğal şekilde kullanılmalı, gereksiz tekrar edilmemeli.\n"
            . "- Amaç: Kullanıcıyı bilgilendirmek + güven oluşturmak + satın alma kararını kolaylaştırmak.\n\n"
            . "2. SEO BAŞLIK KURALLARI:\n"
            . "- Google arama sonuçlarına uygun olmalı.\n"
            . "- Ana anahtar kelime mümkünse başlığın ilk bölümünde yer almalı.\n"
            . "- 50-60 karakter civarında olmalı ve tıklama isteği oluşturmalı.\n"
            . "- Yapay kalıplardan kaçınmalı (Örn: 'Modern Mobilya Seçiminde {$site_name}’yu Tercih Etmeniz İçin 7 Neden').\n\n"
            . "3. MAKALE YAPISI & HTML FORMATI:\n"
            . "- Yanıtını DOĞRUDAN saf HTML biçiminde (<h1>, <h2>, <h3>, <p>, <ul>, <li>, <strong>) oluştur. Kod blokları veya markdown backtick (```html) ASLA ekleme.\n"
            . "- H1: SEO uyumlu ana başlık.\n"
            . "- Giriş: İlk 150 kelimede ana konuyu açıkla, kullanıcının problemini anlat ve markanın çözüm sunduğunu doğal şekilde belirt.\n"
            . "- H2 başlıklar: Arama niyetine uygun hazırlanmalı, her H2 altında en az 2-4 paragraf olmalı.\n"
            . "- H3 başlıklar: Gerektiğinde içeriği bölmeli ve okunabilirliği artırmalı.\n"
            . "- Sonuç: Marka güveni oluşturmalı ve kullanıcıyı harekete/satın almaya yönlendirmeli (Call to Action).\n\n"
            . "4. '7 NEDEN' VE LİSTE MAKALELERİ:\n"
            . "- Eğer başlıkta '5 neden', '7 neden' gibi ifade varsa; alt başlıklar gerçekten neden olmalı (Örn: 1. Kaliteli malzeme kullanımı, 2. Modern tasarım anlayışı, 3. Fonksiyonel kullanım avantajı).\n\n"
            . "5. MARKA BİLGİLERİ & GOOGLE E-E-A-T:\n"
            . "- Marka hakkında sahte bilgiler uydurma. Güvenli, gerçekçi ve deneyim odaklı ifadeler kullan.\n"
            . "- Robotik cümlelerden kaçın ('Yaşam alanlarınıza değer katar' yerine 'Düzenli kullanım sağlayan tasarımlar sayesinde günlük hayatı kolaylaştırır' gibi gerçekçi yaz).\n"
            . "- 'En kaliteli', 'dünyanın en iyi', 'rakipsiz' gibi kanıtsız abartılardan kaçın.\n\n"
            . "6. SSS VE İÇ LİNK ÖNERİLERİ:\n"
            . "- Makalenin sonunda '<h2>SSS - Sıkça Sorulan Sorular</h2>' bölümü ekle ve Google kullanıcılarının gerçek arama niyetlerine uygun en az 5 soru-cevap yaz.\n"
            . "- En altta '<h2>Önerilen İç Linkler</h2>' listesi (<ul><li>) oluştur.\n\n"
            . "7. KESİN YASAKLAR:\n"
            . "- Düşünce süreci (thinking process, analyze input, chain of thought), İngilizce kelime/çeviri veya selamlama cümleleri KESİNLİKLE YAZMA.\n"
            . "- Dil: %100 kusursuz, akıcı, zengin ve profesyonel Türkçe (" . $language . ").";

        $article_prompt = "Konu Başlığı: '{$current_topic}'.\nMarka Adı: '{$site_name}'.\nYazım Tonu: '{$tone}'.\n\nYukarıdaki 13 kurala tam uyarak, Google'da 1. sıraya yerleşecek, marka güveni oluşturacak, minimum 1500 kelimelik, zengin H2-H3 alt başlıkları, SSS ve iç link önerileri içeren tam SEO makalesini oluştur.";

        $article_res = $this->ai_client->generate($article_prompt, $system_prompt, array(
            'provider'   => $provider,
            'max_tokens' => 3500,
            'temperature'=> 0.7,
        ));

        if (!$article_res['success']) {
            $this->log_autopilot_result(sprintf(__('Makale üretilemedi (%s): %s', 'ai-content-seo-assistant'), $current_topic, $article_res['error']), 'error');
            return array('success' => false, 'message' => $article_res['error']);
        }

        $content_html = $article_res['content'];

        // Eğer makale gövdesinde H1 veya H2 ana başlığı varsa onu tam başlık olarak al
        if (preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/i', $content_html, $hMatches)) {
            $extracted_h = $this->clean_title_text($hMatches[1]);
            if (mb_strlen($extracted_h, 'UTF-8') >= 8) {
                $current_topic = $extracted_h;
            }
        }

        // 3. SEO Meta Başlığı ve Açıklamasını Üret
        $meta_title = $this->clean_title_text($current_topic);
        $title_res = $this->ai_client->generate(
            "Konu: '{$current_topic}'. Marka: '{$site_name}'. Bu içerik için Google arama sonuçlarında en yüksek tıklama oranını (CTR) alacak, anahtar kelimeyi içeren 50-60 karakterlik tek bir SEO başlığı yaz. Sadece başlığı döndür, tırnak veya ek açıklama yazma.",
            "Sen profesyonel SEO başlık uzmanısın. Düşünce süreci veya İngilizce çeviri ASLA yazma. Sadece saf Türkçe başlığı döndür.",
            array('provider' => $provider, 'max_tokens' => 80, 'temperature' => 0.5)
        );
        if ($title_res['success']) {
            $cleaned_t = $this->clean_title_text($title_res['content']);
            if (mb_strlen($cleaned_t, 'UTF-8') >= 8) {
                $meta_title = $cleaned_t;
            }
        }

        $meta_desc = '';
        $desc_res = $this->ai_client->generate(
            "Konu: '{$current_topic}'. Marka: '{$site_name}'. Bu yazı için Google aramalarında görünecek, anahtar kelimeyi ve eyleme çağrıyı (CTA) içeren 140-160 karakterlik tek bir SEO meta açıklaması yaz. Sadece açıklamayı döndür.",
            "Sen SEO meta açıklaması uzmanısın. Düşünce süreci veya İngilizce çeviri ASLA yazma. Sadece saf Türkçe açıklamayı döndür.",
            array('provider' => $provider, 'max_tokens' => 150, 'temperature' => 0.5)
        );
        if ($desc_res['success']) {
            $meta_desc = $this->clean_meta_desc($desc_res['content']);
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

    /**
     * Başlık Metnini Temizle (Düşünce Süreci, İngilizce Çeviri, Prefix, tırnak, numara temizleme)
     */
    private function clean_title_text($raw) {
        $t = trim(wp_strip_all_tags($raw), "\"'\n\r#* ");

        // Düşünce süreci veya analyze input / constraint sızıntısını temizle
        if (preg_match('/(?:here[\'’]?s\s+a\s+|s\s+a\s+|\*?\*?)thinking\s+process|analyze\s+user\s+input|constraint\s+\d/iu', $t)) {
            // Önce tırnak içindeki konuyu yakalamayı dene: ‘...’ veya "..."
            if (preg_match('/[‘\'"]([^‘\'"]{10,})[’\'"]/u', $t, $m)) {
                $t = $m[1];
            } elseif (preg_match('/topic:?\s*[‘\'"*]*([^‘\'"*\r\n]{8,})/iu', $t, $mTopic)) {
                $t = $mTopic[1];
            } else {
                $lines = preg_split('/\r?\n/', $t);
                foreach ($lines as $l) {
                    $tl = trim($l);
                    if (!preg_match('/thinking\s+process|analyze|constraint|role:|goal:/iu', $tl) && mb_strlen($tl, 'UTF-8') >= 8) {
                        $t = $tl;
                        break;
                    }
                }
            }
        }

        // Parantez içindeki gereksiz İngilizce çevirileri temizle
        $t = preg_replace('/\([A-Za-z\s,:\'’\-\.\?]{8,}\)/u', '', $t);

        // Prefixleri kaldır
        $t = preg_replace('/^(?:başlık|seo başlığı|title|öneri|konu|topic)\s*:\s*/iu', '', $t);
        $t = preg_replace('/^(\d+[\.\-\)]\s*)/u', '', $t);
        $t = preg_replace('/^[-–—]\s*/u', '', $t);

        return trim($t, "\"'\n\r#* ");
    }

    /**
     * Meta Açıklama Metnini Temizle
     */
    private function clean_meta_desc($raw) {
        $t = trim(wp_strip_all_tags($raw), "\"'\n\r#* ");

        // Düşünce süreci sızıntısını kaldır
        $t = preg_replace('/^(here[\'’]s\s+(a\s+)?thinking\s+process[\s\S]*?(?:\n\n|\r\n\r\n|(?=<h[1-6]|<p)))/iu', '', $t);
        $t = preg_replace('/^(\*?\*?thinking\s+process:?\*?\*?[\s\S]*?(?:\n\n|\r\n\r\n|(?=<h[1-6]|<p)))/iu', '', $t);

        // Parantez içindeki gereksiz İngilizce çevirileri temizle
        $t = preg_replace('/\([A-Za-z\s,:\'’\-\.\?]{8,}\)/u', '', $t);

        $t = preg_replace('/^(açıklama|meta açıklama|meta description|description)\s*:\s*/iu', '', $t);
        return trim($t, "\"'\n\r#* ");
    }
}
