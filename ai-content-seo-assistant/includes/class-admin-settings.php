<?php
/**
 * Eklenti Yönetim Paneli ve Ayarlar Sayfası
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Admin_Settings {

    private $options;

    public function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Ana Yönetici Menüsüne Ekle (Özel Sol Menü & Ayarlar Altı Çift Garanti)
     */
    public function add_plugin_admin_menu() {
        // 1. Sol Ana Menü
        add_menu_page(
            __('AI İçerik & SEO Asistanı', 'ai-content-seo-assistant'),
            __('AI SEO Asistanı', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant',
            array($this, 'render_settings_page'),
            'dashicons-chart-line',
            30
        );

        // 2. Ayarlar Altı (Erişim Kolaylığı)
        add_options_page(
            __('AI İçerik & SEO Asistanı', 'ai-content-seo-assistant'),
            __('AI SEO Asistanı', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-content-seo-assistant',
            __('AI Modelleri & Genel Ayarlar', 'ai-content-seo-assistant'),
            __('AI Modelleri & Ayarlar', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-content-seo-assistant',
            __('⚡ Otomatik Pilot (Cron)', 'ai-content-seo-assistant'),
            __('⚡ Otomatik Pilot', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant-autopilot',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-content-seo-assistant',
            __('🔑 Lisans & Aktivasyon', 'ai-content-seo-assistant'),
            __('🔑 Lisans & Aktivasyon', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant-license',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-content-seo-assistant',
            __('🔄 Otomatik Güncellemeler', 'ai-content-seo-assistant'),
            __('🔄 Güncellemeler', 'ai-content-seo-assistant'),
            'manage_options',
            'ai-content-seo-assistant-updates',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Ayarları Kaydet
     */
    public function register_settings() {
        register_setting('ai_seo_options_group', 'ai_seo_assistant_options', array($this, 'sanitize_settings'));
    }

    /**
     * Ayarları Temizle / Doğrula (Mevcut ayarları koruyarak güncelle)
     */
    public function sanitize_settings($input) {
        $existing = get_option('ai_seo_assistant_options', array());
        $sanitized = is_array($existing) ? $existing : array();

        if (isset($input['default_provider'])) {
            $sanitized['default_provider'] = sanitize_text_field($input['default_provider']);
        }

        // API Keyler ve Modeller
        if (isset($input['groq_key']))         $sanitized['groq_key']         = sanitize_text_field(trim($input['groq_key']));
        if (isset($input['groq_model']))       $sanitized['groq_model']       = sanitize_text_field($input['groq_model']);

        if (isset($input['openai_key']))       $sanitized['openai_key']       = sanitize_text_field(trim($input['openai_key']));
        if (isset($input['openai_model']))     $sanitized['openai_model']     = sanitize_text_field($input['openai_model']);

        if (isset($input['anthropic_key']))    $sanitized['anthropic_key']    = sanitize_text_field(trim($input['anthropic_key']));
        if (isset($input['anthropic_model']))  $sanitized['anthropic_model']  = sanitize_text_field($input['anthropic_model']);

        if (isset($input['gemini_key']))       $sanitized['gemini_key']       = sanitize_text_field(trim($input['gemini_key']));
        if (isset($input['gemini_model']))     $sanitized['gemini_model']     = sanitize_text_field($input['gemini_model']);

        if (isset($input['deepseek_key']))     $sanitized['deepseek_key']     = sanitize_text_field(trim($input['deepseek_key']));
        if (isset($input['deepseek_model']))   $sanitized['deepseek_model']   = sanitize_text_field($input['deepseek_model']);

        if (isset($input['openrouter_key']))   $sanitized['openrouter_key']   = sanitize_text_field(trim($input['openrouter_key']));
        if (isset($input['openrouter_model'])) $sanitized['openrouter_model'] = sanitize_text_field($input['openrouter_model']);

        if (isset($input['custom_base_url']))  $sanitized['custom_base_url']  = esc_url_raw(trim($input['custom_base_url']));
        if (isset($input['custom_key']))       $sanitized['custom_key']       = sanitize_text_field(trim($input['custom_key']));
        if (isset($input['custom_model']))     $sanitized['custom_model']     = sanitize_text_field(trim($input['custom_model']));

        // Genel Parametreler
        if (isset($input['temperature']))      $sanitized['temperature']      = floatval($input['temperature']);
        if (isset($input['max_tokens']))       $sanitized['max_tokens']       = intval($input['max_tokens']);
        if (isset($input['default_tone']))     $sanitized['default_tone']     = sanitize_text_field($input['default_tone']);
        if (isset($input['default_language'])) $sanitized['default_language'] = sanitize_text_field($input['default_language']);

        // SEO Seçenekleri (Form gönderildiğinde checkbox işaretli değilse 0 kaydedilir)
        if (isset($input['_seo_tab_submitted'])) {
            $sanitized['enable_schema']    = !empty($input['enable_schema']) ? 1 : 0;
            $sanitized['enable_opengraph'] = !empty($input['enable_opengraph']) ? 1 : 0;
            $sanitized['enable_twitter']   = !empty($input['enable_twitter']) ? 1 : 0;

            $sanitized['post_types']       = !empty($input['post_types']) && is_array($input['post_types']) 
                ? array_map('sanitize_text_field', $input['post_types']) 
                : array('post', 'page');

            // Otomatik Pilot (Cron) Seçenekleri
            $sanitized['autopilot_enabled']      = !empty($input['autopilot_enabled']) ? 1 : 0;
            $sanitized['autopilot_frequency']    = sanitize_text_field($input['autopilot_frequency'] ?? 'daily');
            $sanitized['autopilot_time']         = sanitize_text_field($input['autopilot_time'] ?? '09:00');
            $sanitized['autopilot_provider']     = sanitize_text_field($input['autopilot_provider'] ?? 'gemini');
            $sanitized['autopilot_status']       = sanitize_text_field($input['autopilot_status'] ?? 'publish');
            $sanitized['autopilot_category']     = intval($input['autopilot_category'] ?? 0);
            $sanitized['autopilot_topics']       = sanitize_textarea_field($input['autopilot_topics'] ?? '');
            $sanitized['autopilot_auto_suggest'] = !empty($input['autopilot_auto_suggest']) ? 1 : 0;

            // Cron zamanlayıcısını güncelle
            if (class_exists('AI_SEO_Cron_Autopilot')) {
                AI_SEO_Cron_Autopilot::reschedule_cron($sanitized);
            }
        }

        return $sanitized;
    }

    /**
     * Ayarlar Sayfası HTML Çıktısı
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $opts = get_option('ai_seo_assistant_options', array());
        $current_page = sanitize_text_field($_GET['page'] ?? 'ai-content-seo-assistant');
        $active_tab = 'providers';
        if ($current_page === 'ai-content-seo-assistant-autopilot') {
            $active_tab = 'autopilot';
        } elseif ($current_page === 'ai-content-seo-assistant-license') {
            $active_tab = 'license';
        } elseif ($current_page === 'ai-content-seo-assistant-updates') {
            $active_tab = 'updates';
        }
        ?>
        <div class="wrap ai-seo-admin-wrap">
            <div class="ai-seo-header">
                <div class="ai-seo-header-title">
                    <span class="dashicons dashicons-superhero"></span>
                    <h1><?php echo esc_html__('AI İçerik & SEO Asistanı', 'ai-content-seo-assistant'); ?></h1>
                    <span class="ai-seo-version-badge">v<?php echo esc_html(AI_SEO_VERSION); ?></span>
                </div>
                <p class="ai-seo-header-desc">
                    <?php echo esc_html__('Yapay zeka modellerini kullanarak tek tıkla zengin makaleler, SEO meta etiketleri, Schema yapısal verisi ve otomatik günlük blog içerikleri üretin.', 'ai-content-seo-assistant'); ?>
                </p>
            </div>

            <?php settings_errors(); ?>

            <nav class="nav-tab-wrapper ai-seo-nav-tabs">
                <a href="#tab-providers" class="nav-tab <?php echo ($active_tab === 'providers') ? 'nav-tab-active' : ''; ?>" data-tab="providers">
                    <span class="dashicons dashicons-cloud"></span> <?php esc_html_e('AI Sağlayıcıları & Anahtarlar', 'ai-content-seo-assistant'); ?>
                </a>
                <a href="#tab-autopilot" class="nav-tab <?php echo ($active_tab === 'autopilot') ? 'nav-tab-active' : ''; ?>" data-tab="autopilot">
                    <span class="dashicons dashicons-clock"></span> <?php esc_html_e('⚡ Otomatik Pilot (Cron)', 'ai-content-seo-assistant'); ?>
                </a>
                <a href="#tab-general" class="nav-tab <?php echo ($active_tab === 'general') ? 'nav-tab-active' : ''; ?>" data-tab="general">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('İçerik & Model Ayarları', 'ai-content-seo-assistant'); ?>
                </a>
                <a href="#tab-seo" class="nav-tab <?php echo ($active_tab === 'seo') ? 'nav-tab-active' : ''; ?>" data-tab="seo">
                    <span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('SEO & Schema.org Ayarları', 'ai-content-seo-assistant'); ?>
                </a>
                <a href="#tab-updates" class="nav-tab <?php echo ($active_tab === 'updates') ? 'nav-tab-active' : ''; ?>" data-tab="updates">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('🔄 Otomatik Güncelleme', 'ai-content-seo-assistant'); ?>
                </a>
                <a href="#tab-license" class="nav-tab <?php echo ($active_tab === 'license') ? 'nav-tab-active' : ''; ?>" data-tab="license">
                    <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('🔑 Lisans & Aktivasyon', 'ai-content-seo-assistant'); ?>
                </a>
            </nav>

            <form action="options.php" method="post" class="ai-seo-settings-form">
                <?php
                settings_fields('ai_seo_options_group');
                ?>
                <input type="hidden" name="ai_seo_assistant_options[_seo_tab_submitted]" value="1" />

                <!-- 1. TAB: AI SAĞLAYICILARI -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'providers') ? 'active' : ''; ?>" id="tab-pane-providers" style="<?php echo ($active_tab === 'providers') ? 'display:block;' : 'display:none;'; ?>">

                        <!-- Varsayılan Sağlayıcı Seçimi -->
                        <div class="ai-seo-card ai-seo-card-primary">
                            <h3><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Varsayılan AI Sağlayıcısı', 'ai-content-seo-assistant'); ?></h3>
                            <p class="description"><?php esc_html_e('İçerik ve SEO üretiminde öncelikli kullanılacak sağlayıcıyı seçin.', 'ai-content-seo-assistant'); ?></p>
                            <select name="ai_seo_assistant_options[default_provider]" id="default_provider" class="regular-text">
                                <option value="groq" <?php selected($opts['default_provider'] ?? '', 'groq'); ?>>Groq (Ultra Hızlı - Llama 3.3 70B & DeepSeek R1)</option>
                                <option value="gemini" <?php selected($opts['default_provider'] ?? 'gemini', 'gemini'); ?>>Google Gemini (gemini-2.5-flash / Pro)</option>
                                <option value="deepseek" <?php selected($opts['default_provider'] ?? '', 'deepseek'); ?>>DeepSeek (deepseek-chat / reasoner)</option>
                                <option value="openrouter" <?php selected($opts['default_provider'] ?? '', 'openrouter'); ?>>OpenRouter (Ücretsiz & Ücretli Modeller)</option>
                                <option value="anthropic" <?php selected($opts['default_provider'] ?? '', 'anthropic'); ?>>Anthropic Claude (3.7 Sonnet / Haiku)</option>
                                <option value="openai" <?php selected($opts['default_provider'] ?? '', 'openai'); ?>>OpenAI (GPT-4o-mini / GPT-4o / o3-mini)</option>
                                <option value="custom" <?php selected($opts['default_provider'] ?? '', 'custom'); ?>>Özel / Anthropic Uyumlu (Z.ai, MiniMax, Yerel LLM)</option>
                            </select>
                        </div>

                        <!-- Groq -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>Groq</h3>
                                <span class="ai-badge ai-badge-fast"><?php esc_html_e('Ultra Hızlı / 500+ t/s', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('Işık hızında Llama 3.3 70B ve DeepSeek R1 modelleri (Ücretsiz kota mevcuttur).', 'ai-content-seo-assistant'); ?> <a href="https://console.groq.com/keys" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="groq_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="groq_key" name="ai_seo_assistant_options[groq_key]" value="<?php echo esc_attr($opts['groq_key'] ?? ''); ?>" class="regular-text" placeholder="gsk_..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="groq_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="groq_model" name="ai_seo_assistant_options[groq_model]" class="regular-text">
                                    <option value="llama-3.1-8b-instant" <?php selected($opts['groq_model'] ?? 'llama-3.1-8b-instant', 'llama-3.1-8b-instant'); ?>>llama-3.1-8b-instant (★ Ultra Hızlı & %100 Açık - 800 t/s - Önerilen)</option>
                                    <option value="llama-3.3-70b-versatile" <?php selected($opts['groq_model'] ?? '', 'llama-3.3-70b-versatile'); ?>>llama-3.3-70b-versatile (Llama 3.3 70B - Çok Güçlü)</option>
                                    <option value="llama3-70b-8192" <?php selected($opts['groq_model'] ?? '', 'llama3-70b-8192'); ?>>llama3-70b-8192 (Llama 3 70B Kararlı)</option>
                                    <option value="llama3-8b-8192" <?php selected($opts['groq_model'] ?? '', 'llama3-8b-8192'); ?>>llama3-8b-8192 (Llama 3 8B)</option>
                                    <option value="deepseek-r1-distill-llama-70b" <?php selected($opts['groq_model'] ?? '', 'deepseek-r1-distill-llama-70b'); ?>>deepseek-r1-distill-llama-70b (DeepSeek R1 Muhakeme)</option>
                                    <option value="gemma2-9b-it" <?php selected($opts['groq_model'] ?? '', 'gemma2-9b-it'); ?>>gemma2-9b-it (Google Gemma 2)</option>
                                    <option value="mixtral-8x7b-32768" <?php selected($opts['groq_model'] ?? '', 'mixtral-8x7b-32768'); ?>>mixtral-8x7b-32768 (Geniş Bağlam)</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="groq"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- OpenRouter -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>OpenRouter</h3>
                                <span class="ai-badge ai-badge-free"><?php esc_html_e('Ücretsiz Modeller', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('Tek API anahtarı ile Gemini 2.0, DeepSeek R1, Llama 3.3 ve onlarca modeli kullanın.', 'ai-content-seo-assistant'); ?> <a href="https://openrouter.ai/settings/keys" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="openrouter_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="openrouter_key" name="ai_seo_assistant_options[openrouter_key]" value="<?php echo esc_attr($opts['openrouter_key'] ?? ''); ?>" class="regular-text" placeholder="sk-or-v1-..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="openrouter_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="openrouter_model" name="ai_seo_assistant_options[openrouter_model]" class="regular-text">
                                    <option value="google/gemini-2.0-flash-exp:free" <?php selected($opts['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free', 'google/gemini-2.0-flash-exp:free'); ?>>google/gemini-2.0-flash-exp:free (★ Gemini 2.0 Flash - Ücretsiz & Hızlı)</option>
                                    <option value="meta-llama/llama-3.3-70b-instruct:free" <?php selected($opts['openrouter_model'] ?? '', 'meta-llama/llama-3.3-70b-instruct:free'); ?>>meta-llama/llama-3.3-70b-instruct:free (Llama 3.3 70B - Ücretsiz)</option>
                                    <option value="deepseek/deepseek-r1:free" <?php selected($opts['openrouter_model'] ?? '', 'deepseek/deepseek-r1:free'); ?>>deepseek/deepseek-r1:free (DeepSeek R1 - Ücretsiz)</option>
                                    <option value="deepseek/deepseek-chat:free" <?php selected($opts['openrouter_model'] ?? '', 'deepseek/deepseek-chat:free'); ?>>deepseek/deepseek-chat:free (DeepSeek V3 - Ücretsiz)</option>
                                    <option value="qwen/qwen-2.5-coder-32b-instruct:free" <?php selected($opts['openrouter_model'] ?? '', 'qwen/qwen-2.5-coder-32b-instruct:free'); ?>>qwen/qwen-2.5-coder-32b-instruct:free (Qwen 2.5 Coder - Ücretsiz)</option>
                                    <option value="meta-llama/llama-3.1-8b-instruct:free" <?php selected($opts['openrouter_model'] ?? '', 'meta-llama/llama-3.1-8b-instruct:free'); ?>>meta-llama/llama-3.1-8b-instruct:free (Llama 3.1 8B - Ücretsiz)</option>
                                    <option value="mistralai/mistral-7b-instruct:free" <?php selected($opts['openrouter_model'] ?? '', 'mistralai/mistral-7b-instruct:free'); ?>>mistralai/mistral-7b-instruct:free (Mistral 7B - Ücretsiz)</option>
                                    <option value="openrouter/auto" <?php selected($opts['openrouter_model'] ?? '', 'openrouter/auto'); ?>>openrouter/auto (OpenRouter Otomatik Yönlendirici)</option>
                                    <option value="deepseek/deepseek-chat" <?php selected($opts['openrouter_model'] ?? '', 'deepseek/deepseek-chat'); ?>>deepseek/deepseek-chat (DeepSeek V3 - Ücretli / Bakiye ile)</option>
                                    <option value="anthropic/claude-3.5-haiku" <?php selected($opts['openrouter_model'] ?? '', 'anthropic/claude-3.5-haiku'); ?>>anthropic/claude-3.5-haiku (Claude 3.5 Haiku - Ücretli)</option>
                                    <option value="openai/gpt-4o-mini" <?php selected($opts['openrouter_model'] ?? '', 'openai/gpt-4o-mini'); ?>>openai/gpt-4o-mini (GPT-4o Mini - Ücretli)</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="openrouter"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- Google Gemini -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>Google Gemini</h3>
                                <span class="ai-badge ai-badge-fast"><?php esc_html_e('Hızlı & Çok Güçlü', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('Google AI Studio üzerinden ücretsiz API anahtarı alabilirsiniz.', 'ai-content-seo-assistant'); ?> <a href="https://aistudio.google.com/app/apikey" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="gemini_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="gemini_key" name="ai_seo_assistant_options[gemini_key]" value="<?php echo esc_attr($opts['gemini_key'] ?? ''); ?>" class="regular-text" placeholder="AIzaSy..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="gemini_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="gemini_model" name="ai_seo_assistant_options[gemini_model]" class="regular-text">
                                    <option value="gemini-2.5-flash" <?php selected($opts['gemini_model'] ?? 'gemini-2.5-flash', 'gemini-2.5-flash'); ?>>gemini-2.5-flash (★ En Yeni Standart Model - Önerilen)</option>
                                    <option value="gemini-1.5-flash" <?php selected($opts['gemini_model'] ?? '', 'gemini-1.5-flash'); ?>>gemini-1.5-flash (Hızlı & Kararlı)</option>
                                    <option value="gemini-2.5-pro" <?php selected($opts['gemini_model'] ?? '', 'gemini-2.5-pro'); ?>>gemini-2.5-pro (Gelişmiş Düşünme)</option>
                                    <option value="gemini-1.5-pro" <?php selected($opts['gemini_model'] ?? '', 'gemini-1.5-pro'); ?>>gemini-1.5-pro (Geniş Bağlam)</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="gemini"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- DeepSeek -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>DeepSeek</h3>
                                <span class="ai-badge ai-badge-cheap"><?php esc_html_e('Ekonomik / 10x Ucuz', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('DeepSeek V3 / R1 modellerini doğrudan resmi API üzerinden kullanın.', 'ai-content-seo-assistant'); ?> <a href="https://platform.deepseek.com/api_keys" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="deepseek_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="deepseek_key" name="ai_seo_assistant_options[deepseek_key]" value="<?php echo esc_attr($opts['deepseek_key'] ?? ''); ?>" class="regular-text" placeholder="sk-..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="deepseek_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="deepseek_model" name="ai_seo_assistant_options[deepseek_model]" class="regular-text">
                                    <option value="deepseek-chat" <?php selected($opts['deepseek_model'] ?? 'deepseek-chat', 'deepseek-chat'); ?>>deepseek-chat (★ DeepSeek V3 - Önerilen)</option>
                                    <option value="deepseek-reasoner" <?php selected($opts['deepseek_model'] ?? '', 'deepseek-reasoner'); ?>>deepseek-reasoner (DeepSeek R1 Muhakeme)</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="deepseek"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- Anthropic Claude -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>Anthropic Claude</h3>
                                <span class="ai-badge ai-badge-premium"><?php esc_html_e('Akıllı & Doğal Dil', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('Claude 3.7 Sonnet ve Claude 3.5 Haiku modelleri.', 'ai-content-seo-assistant'); ?> <a href="https://console.anthropic.com/settings/keys" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="anthropic_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="anthropic_key" name="ai_seo_assistant_options[anthropic_key]" value="<?php echo esc_attr($opts['anthropic_key'] ?? ''); ?>" class="regular-text" placeholder="sk-ant-..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="anthropic_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="anthropic_model" name="ai_seo_assistant_options[anthropic_model]" class="regular-text">
                                    <option value="claude-3-7-sonnet-20250219" <?php selected($opts['anthropic_model'] ?? 'claude-3-7-sonnet-20250219', 'claude-3-7-sonnet-20250219'); ?>>Claude 3.7 Sonnet (★ En Yetenekli & Güncel)</option>
                                    <option value="claude-3-5-haiku-20241022" <?php selected($opts['anthropic_model'] ?? '', 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (Hızlı & Ekonomik)</option>
                                    <option value="claude-3-5-sonnet-20241022" <?php selected($opts['anthropic_model'] ?? '', 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="anthropic"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- OpenAI -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3>OpenAI</h3>
                                <span class="ai-badge ai-badge-popular"><?php esc_html_e('Popüler', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('GPT-4o, GPT-4o-mini ve o3-mini modelleri.', 'ai-content-seo-assistant'); ?> <a href="https://platform.openai.com/api-keys" target="_blank"><?php esc_html_e('Anahtar Al', 'ai-content-seo-assistant'); ?> &rarr;</a></p>
                            <div class="ai-field-group">
                                <label for="openai_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="openai_key" name="ai_seo_assistant_options[openai_key]" value="<?php echo esc_attr($opts['openai_key'] ?? ''); ?>" class="regular-text" placeholder="sk-proj-..." />
                            </div>
                            <div class="ai-field-group">
                                <label for="openai_model"><?php esc_html_e('Model:', 'ai-content-seo-assistant'); ?></label>
                                <select id="openai_model" name="ai_seo_assistant_options[openai_model]" class="regular-text">
                                    <option value="gpt-4o-mini" <?php selected($opts['openai_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini'); ?>>GPT-4o Mini (★ Hızlı & Ekonomik - Önerilen)</option>
                                    <option value="gpt-4o" <?php selected($opts['openai_model'] ?? '', 'gpt-4o'); ?>>GPT-4o (Amiral Gemisi)</option>
                                    <option value="o3-mini" <?php selected($opts['openai_model'] ?? '', 'o3-mini'); ?>>o3-mini (Akıllı Muhakeme)</option>
                                </select>
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="openai"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                        <!-- Özel / Z.ai / MiniMax / Local LLM -->
                        <div class="ai-seo-card">
                            <div class="ai-seo-card-header">
                                <h3><?php esc_html_e('Özel Uç Nokta (Z.ai, MiniMax, Yerel)', 'ai-content-seo-assistant'); ?></h3>
                                <span class="ai-badge ai-badge-custom"><?php esc_html_e('Özel Yapılandırma', 'ai-content-seo-assistant'); ?></span>
                            </div>
                            <p class="description"><?php esc_html_e('Anthropic veya OpenAI uyumlu herhangi bir özel API endpoint\'i girin.', 'ai-content-seo-assistant'); ?></p>
                            <div class="ai-field-group">
                                <label for="custom_base_url"><?php esc_html_e('Base URL:', 'ai-content-seo-assistant'); ?></label>
                                <input type="text" id="custom_base_url" name="ai_seo_assistant_options[custom_base_url]" value="<?php echo esc_attr($opts['custom_base_url'] ?? ''); ?>" class="regular-text" placeholder="https://api.z.ai/api/anthropic veya http://localhost:11434" />
                            </div>
                            <div class="ai-field-group">
                                <label for="custom_key"><?php esc_html_e('API Anahtarı:', 'ai-content-seo-assistant'); ?></label>
                                <input type="password" id="custom_key" name="ai_seo_assistant_options[custom_key]" value="<?php echo esc_attr($opts['custom_key'] ?? ''); ?>" class="regular-text" />
                            </div>
                            <div class="ai-field-group">
                                <label for="custom_model"><?php esc_html_e('Model İsmi:', 'ai-content-seo-assistant'); ?></label>
                                <input type="text" id="custom_model" name="ai_seo_assistant_options[custom_model]" value="<?php echo esc_attr($opts['custom_model'] ?? ''); ?>" class="regular-text" placeholder="glm-4.7-flash, MiniMax-M2.7 vb." />
                            </div>
                            <button type="button" class="button ai-test-api-btn" data-provider="custom"><?php esc_html_e('Bağlantıyı Test Et', 'ai-content-seo-assistant'); ?></button>
                            <span class="ai-test-status"></span>
                        </div>

                    </div>
                </div>

                <!-- 2. TAB: OTOMATİK PİLOT (CRON İLE GÜNLÜK MAKALE) -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'autopilot') ? 'active' : ''; ?>" id="tab-pane-autopilot" style="<?php echo ($active_tab === 'autopilot') ? 'display:block;' : 'display:none;'; ?>">
                    
                    <div class="ai-seo-autopilot-banner">
                        <div class="ai-autopilot-info">
                            <h3><span class="dashicons dashicons-clock"></span> <?php esc_html_e('Otomatik Pilot & Zamanlanmış Makale Üretici', 'ai-content-seo-assistant'); ?></h3>
                            <p><?php esc_html_e('Bu özellik sayesinde WordPress arka planda (WP-Cron) otomatik uyanır, belirlediğiniz sıklıkta (örn: günde 1 makale) konu havuzundan sırayla başlık, tam makale ve SEO meta verilerini üreterek sitenizde otomatik paylaşır.', 'ai-content-seo-assistant'); ?></p>
                        </div>
                        <div class="ai-autopilot-quick-test">
                            <button type="button" id="ai-btn-trigger-autopilot-now" class="button button-primary button-hero">
                                <span class="dashicons dashicons-update ai-spin-icon" style="display:none;"></span>
                                <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Şimdi 1 Makale Üret (Test Et)', 'ai-content-seo-assistant'); ?>
                            </button>
                            <span id="ai-autopilot-test-status" class="ai-test-status"></span>
                        </div>
                    </div>

                    <table class="form-table ai-seo-form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Otomatik Pilot Durumu', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <label style="font-weight:600; font-size:14px; color:#1d2327;">
                                    <input type="checkbox" name="ai_seo_assistant_options[autopilot_enabled]" value="1" <?php checked(!empty($opts['autopilot_enabled'])); ?> />
                                    <?php esc_html_e('Otomatik pilotu aktif et (Zamanlanmış makale üretimi başlasın)', 'ai-content-seo-assistant'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Aktif edildiğinde WordPress her gün belirlenen saatte yeni bir makale yazar.', 'ai-content-seo-assistant'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_frequency"><?php esc_html_e('Paylaşım Sıklığı', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <select name="ai_seo_assistant_options[autopilot_frequency]" id="autopilot_frequency" class="regular-text">
                                    <option value="daily" <?php selected($opts['autopilot_frequency'] ?? 'daily', 'daily'); ?>><?php esc_html_e('Günde 1 Makale (Önerilen)', 'ai-content-seo-assistant'); ?></option>
                                    <option value="twicedaily" <?php selected($opts['autopilot_frequency'] ?? '', 'twicedaily'); ?>><?php esc_html_e('Günde 2 Makale', 'ai-content-seo-assistant'); ?></option>
                                    <option value="every_2_days" <?php selected($opts['autopilot_frequency'] ?? '', 'every_2_days'); ?>><?php esc_html_e('2 Günde 1 Makale', 'ai-content-seo-assistant'); ?></option>
                                    <option value="every_3_days" <?php selected($opts['autopilot_frequency'] ?? '', 'every_3_days'); ?>><?php esc_html_e('3 Günde 1 Makale', 'ai-content-seo-assistant'); ?></option>
                                    <option value="weekly" <?php selected($opts['autopilot_frequency'] ?? '', 'weekly'); ?>><?php esc_html_e('Haftada 1 Makale', 'ai-content-seo-assistant'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_time"><?php esc_html_e('Günlük Çalışma Saati', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <input type="time" name="ai_seo_assistant_options[autopilot_time]" id="autopilot_time" value="<?php echo esc_attr($opts['autopilot_time'] ?? '09:00'); ?>" class="regular-text" style="max-width:140px;" />
                                <span class="description"><?php esc_html_e('Makalenin üretileceği saat (Örn: 09:00)', 'ai-content-seo-assistant'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_provider"><?php esc_html_e('Kullanılacak Yapay Zeka', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <select name="ai_seo_assistant_options[autopilot_provider]" id="autopilot_provider" class="regular-text">
                                    <option value="groq" <?php selected($opts['autopilot_provider'] ?? '', 'groq'); ?>>Groq (Llama 3.3 70B & DeepSeek R1 - Ultra Hızlı)</option>
                                    <option value="gemini" <?php selected($opts['autopilot_provider'] ?? 'gemini', 'gemini'); ?>>Google Gemini (gemini-2.5-flash - Çok Hızlı & Ücretsiz Kotası Yüksek)</option>
                                    <option value="deepseek" <?php selected($opts['autopilot_provider'] ?? '', 'deepseek'); ?>>DeepSeek (deepseek-chat V3 - Ekonomik & Zeki)</option>
                                    <option value="openrouter" <?php selected($opts['autopilot_provider'] ?? '', 'openrouter'); ?>>OpenRouter (Gemini 2.0 / Llama 3.3 / Qwen)</option>
                                    <option value="anthropic" <?php selected($opts['autopilot_provider'] ?? '', 'anthropic'); ?>>Anthropic Claude (3.7 Sonnet / 3.5 Haiku)</option>
                                    <option value="openai" <?php selected($opts['autopilot_provider'] ?? '', 'openai'); ?>>OpenAI (gpt-4o-mini / gpt-4o / o3-mini)</option>
                                    <option value="custom" <?php selected($opts['autopilot_provider'] ?? '', 'custom'); ?>>Özel Uç Nokta (Z.ai, MiniMax, Yerel LLM)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_status"><?php esc_html_e('Üretilen Makale Durumu', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <select name="ai_seo_assistant_options[autopilot_status]" id="autopilot_status" class="regular-text">
                                    <option value="publish" <?php selected($opts['autopilot_status'] ?? 'publish', 'publish'); ?>><?php esc_html_e('Doğrudan Yayınla (Hemen Sitede Görünsün)', 'ai-content-seo-assistant'); ?></option>
                                    <option value="draft" <?php selected($opts['autopilot_status'] ?? '', 'draft'); ?>><?php esc_html_e('Taslak Olarak Kaydet (Ben İnceleyip Yayınlayayım)', 'ai-content-seo-assistant'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_category"><?php esc_html_e('Hedef Kategori', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <?php
                                $categories = get_categories(array('hide_empty' => false));
                                ?>
                                <select name="ai_seo_assistant_options[autopilot_category]" id="autopilot_category" class="regular-text">
                                    <option value="0"><?php esc_html_e('— Varsayılan Kategori —', 'ai-content-seo-assistant'); ?></option>
                                    <?php foreach ($categories as $cat) : ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($opts['autopilot_category'] ?? 0, $cat->term_id); ?>>
                                            <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Otomatik üretilen yazıların ekleneceği kategoriyi seçin.', 'ai-content-seo-assistant'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="autopilot_topics"><?php esc_html_e('Konu & Anahtar Kelime Havuzu', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <?php
                                $default_topics = "Yer Sofrası Seçerken Dikkat Edilmesi Gerekenler ve Ahşap Modeller\nKüçük Odalar İçin Katlanır Yemek Masası Avantajları\nGeleneksel Yer Sofrası Kültürü ve Modern Ev Dekorasyonundaki Yeri\nAhşap Mobilya Temizliği ve Parlatma Yöntemleri\nBalkon ve Bahçe İçin Katlanabilir Ahşap Masa Modelleri\nDar Mutfaklar İçin Pratik Alan Kazandıran Masa Çözümleri\nDoğal Ahşap Mobilyaların Ev Sağlığına ve Enerjisine Faydaları\n6 Kişilik Katlanır Yer Sofrası Modelleri ve Kullanım İpuçları\nRustik ve Ahşap Dekorasyonda En Çok Tercih Edilen Renk Kombinasyonları\nEvde Aile ve Misafirlerle Yer Sofrasında Yemek Yemenin Keyfi";
                                $topics_val = isset($opts['autopilot_topics']) ? $opts['autopilot_topics'] : $default_topics;
                                ?>
                                <textarea name="ai_seo_assistant_options[autopilot_topics]" id="autopilot_topics" rows="10" class="large-text code" placeholder="<?php esc_attr_e("Her satıra 1 konu veya anahtar kelime yazın...\nÖrn:\nAhşap Yer Sofrası Modelleri\nKatlanır Yemek Masası", 'ai-content-seo-assistant'); ?>"><?php echo esc_textarea($topics_val); ?></textarea>
                                <p class="description"><?php esc_html_e('Her gün sıradaki en üstteki konu alınır, makale üretilir ve listeden otomatik düşülür.', 'ai-content-seo-assistant'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Otomatik Konu Türetme (Auto-Suggest)', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_seo_assistant_options[autopilot_auto_suggest]" value="1" <?php checked(!empty($opts['autopilot_auto_suggest'])); ?> />
                                    <?php esc_html_e('Konu havuzundaki liste bittiğinde Yapay Zeka kendi kendine yeni güncel mobilya/dekorasyon konuları türeterek yazmaya devam etsin.', 'ai-content-seo-assistant'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <!-- Son Otomatik Pilot İşlemleri Günlüğü -->
                    <div class="ai-autopilot-logs-section" style="margin-top:30px;">
                        <h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Son Otomatik Pilot İşlem Geçmişi', 'ai-content-seo-assistant'); ?></h3>
                        <?php
                        $logs = get_option('ai_seo_autopilot_logs', array());
                        if (!empty($logs)) :
                        ?>
                            <table class="widefat striped" style="margin-top:10px;">
                                <thead>
                                    <tr>
                                        <th style="width:170px;"><?php esc_html_e('Tarih & Saat', 'ai-content-seo-assistant'); ?></th>
                                        <th><?php esc_html_e('İşlem Mesajı', 'ai-content-seo-assistant'); ?></th>
                                        <th style="width:100px;"><?php esc_html_e('Durum', 'ai-content-seo-assistant'); ?></th>
                                        <th style="width:120px;"><?php esc_html_e('Yazıyı Gör', 'ai-content-seo-assistant'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($logs, 0, 10) as $log) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($log['time']); ?></code></td>
                                            <td><?php echo esc_html($log['message']); ?></td>
                                            <td>
                                                <?php if ($log['type'] === 'success') : ?>
                                                    <span class="ai-badge ai-badge-fast"><?php esc_html_e('Başarılı', 'ai-content-seo-assistant'); ?></span>
                                                <?php else : ?>
                                                    <span class="ai-badge ai-badge-premium"><?php esc_html_e('Hata', 'ai-content-seo-assistant'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($log['post_id'])) : ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link($log['post_id'])); ?>" class="button button-small" target="_blank"><?php esc_html_e('Düzenle', 'ai-content-seo-assistant'); ?> &rarr;</a>
                                                <?php else : ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p style="color:#646970; font-style:italic;"><?php esc_html_e('Henüz otomatik pilot işlemi gerçekleşmedi.', 'ai-content-seo-assistant'); ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- 3. TAB: İÇERİK & MODEL AYARLARI -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'general') ? 'active' : ''; ?>" id="tab-pane-general" style="<?php echo ($active_tab === 'general') ? 'display:block;' : 'display:none;'; ?>">
                    <table class="form-table ai-seo-form-table">
                        <tr>
                            <th scope="row"><label for="default_language"><?php esc_html_e('Varsayılan Üretim Dili', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <select name="ai_seo_assistant_options[default_language]" id="default_language" class="regular-text">
                                    <option value="tr" <?php selected($opts['default_language'] ?? '', 'tr'); ?>>Türkçe</option>
                                    <option value="en" <?php selected($opts['default_language'] ?? '', 'en'); ?>>English</option>
                                    <option value="de" <?php selected($opts['default_language'] ?? '', 'de'); ?>>Deutsch</option>
                                    <option value="es" <?php selected($opts['default_language'] ?? '', 'es'); ?>>Español</option>
                                    <option value="fr" <?php selected($opts['default_language'] ?? '', 'fr'); ?>>Français</option>
                                    <option value="ar" <?php selected($opts['default_language'] ?? '', 'ar'); ?>>العربية (Arapça)</option>
                                    <option value="ru" <?php selected($opts['default_language'] ?? '', 'ru'); ?>>Русский (Rusça)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="default_tone"><?php esc_html_e('İçerik Yazım Tonu', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <select name="ai_seo_assistant_options[default_tone]" id="default_tone" class="regular-text">
                                    <option value="professional" <?php selected($opts['default_tone'] ?? 'professional', 'professional'); ?>><?php esc_html_e('Profesyonel & Kurumsal (Önerilen)', 'ai-content-seo-assistant'); ?></option>
                                    <option value="friendly" <?php selected($opts['default_tone'] ?? '', 'friendly'); ?>><?php esc_html_e('Samimi & Akıcı', 'ai-content-seo-assistant'); ?></option>
                                    <option value="informative" <?php selected($opts['default_tone'] ?? '', 'informative'); ?>><?php esc_html_e('Bilgilendirici & Eğitici', 'ai-content-seo-assistant'); ?></option>
                                    <option value="persuasive" <?php selected($opts['default_tone'] ?? '', 'persuasive'); ?>><?php esc_html_e('Pazarlama & İkna Edici', 'ai-content-seo-assistant'); ?></option>
                                    <option value="creative" <?php selected($opts['default_tone'] ?? '', 'creative'); ?>><?php esc_html_e('Yaratıcı & Özgün', 'ai-content-seo-assistant'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="temperature"><?php esc_html_e('Yaratıcılık (Temperature)', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <input type="number" id="temperature" name="ai_seo_assistant_options[temperature]" min="0" max="1" step="0.1" value="<?php echo esc_attr($opts['temperature'] ?? '0.7'); ?>" class="small-text" />
                                <span class="description"><?php esc_html_e('0.0 (Tam odaklı/gerçekçi) ile 1.0 (Çok yaratıcı) arasında değer.', 'ai-content-seo-assistant'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_tokens"><?php esc_html_e('Maksimum Token Sayısı', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <input type="number" id="max_tokens" name="ai_seo_assistant_options[max_tokens]" min="250" max="8000" step="100" value="<?php echo esc_attr($opts['max_tokens'] ?? '2000'); ?>" class="regular-text" />
                                <span class="description"><?php esc_html_e('Tek seferde üretilecek maksimum içerik uzunluğu.', 'ai-content-seo-assistant'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 4. TAB: SEO & SCHEMA AYARLARI -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'seo') ? 'active' : ''; ?>" id="tab-pane-seo" style="<?php echo ($active_tab === 'seo') ? 'display:block;' : 'display:none;'; ?>">
                    <table class="form-table ai-seo-form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Aktif Yazı Tipleri (Post Types)', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <?php
                                $all_post_types = get_post_types(array('public' => true), 'objects');
                                $selected_types = $opts['post_types'] ?? array('post', 'page');
                                foreach ($all_post_types as $pt) {
                                    if ($pt->name === 'attachment') continue;
                                    $checked = in_array($pt->name, $selected_types) ? 'checked' : '';
                                    echo '<label style="margin-right:15px;"><input type="checkbox" name="ai_seo_assistant_options[post_types][]" value="' . esc_attr($pt->name) . '" ' . $checked . '> ' . esc_html($pt->label) . '</label>';
                                }
                                ?>
                                <p class="description"><?php esc_html_e('AI SEO asistanı panelinin hangi içerik türlerinde görüntüleneceğini seçin.', 'ai-content-seo-assistant'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Schema.org JSON-LD Yapısal Veri', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_seo_assistant_options[enable_schema]" value="1" <?php checked(!empty($opts['enable_schema'])); ?> />
                                    <?php esc_html_e('Yazılarda ve sayfalarda otomatik Schema.org Article/BlogPosting JSON-LD çıktısı üret.', 'ai-content-seo-assistant'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('OpenGraph (Facebook / WhatsApp)', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_seo_assistant_options[enable_opengraph]" value="1" <?php checked(!empty($opts['enable_opengraph'])); ?> />
                                    <?php esc_html_e('Sosyal medya paylaşımları için og:title, og:description ve og:image etiketlerini ekle.', 'ai-content-seo-assistant'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Twitter Cards', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="ai_seo_assistant_options[enable_twitter]" value="1" <?php checked(!empty($opts['enable_twitter'])); ?> />
                                    <?php esc_html_e('Twitter/X kartları için meta etiketlerini ekle.', 'ai-content-seo-assistant'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 5. TAB: OTOMATİK GÜNCELLEME SİSTEMİ -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'updates') ? 'active' : ''; ?>" id="tab-pane-updates" style="<?php echo ($active_tab === 'updates') ? 'display:block;' : 'display:none;'; ?>">
                    <div class="ai-seo-card ai-seo-card-primary" style="margin-bottom:20px;">
                        <h3><span class="dashicons dashicons-cloud-saved"></span> <?php esc_html_e('Merkezi Otomatik Güncelleme Sistemi', 'ai-content-seo-assistant'); ?></h3>
                        <p style="font-size:14px; line-height:1.6; color:#3c434a;">
                            <?php esc_html_e('Bu eklenti, tek bir merkezden (GitHub veya kendi sunucunuz) tüm WordPress sitelerinize otomatik güncelleme dağıtacak şekilde geliştirilmiştir. Yeni bir sürüm yayınladığınızda tüm sitelerinizde standart WordPress "Şimdi Güncelle" bildirimi görüntülenir.', 'ai-content-seo-assistant'); ?>
                        </p>
                    </div>

                    <table class="form-table ai-seo-form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Yazılımcı / Geliştirici', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <strong>Serkan AKKAYA</strong> &mdash; <a href="https://misteknoloji360.com.tr/" target="_blank">misteknoloji360.com.tr &rarr;</a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Kurulu Sürüm', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <span class="ai-badge ai-badge-popular" style="font-size:13px; padding:4px 10px;">v<?php echo esc_html(AI_SEO_VERSION); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="update_server_url"><?php esc_html_e('Güncelleme Manifest URL (JSON)', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <input type="url" id="update_server_url" name="ai_seo_assistant_options[update_server_url]" value="<?php echo esc_attr($opts['update_server_url'] ?? 'https://raw.githubusercontent.com/akkaya6611/ai-content-seo-assistant/main/update-info.json'); ?>" class="large-text" placeholder="https://raw.githubusercontent.com/.../update-info.json" />
                                <p class="description">
                                    <?php esc_html_e('Eklentinin yeni sürüm kontrolü yapacağı JSON dosyasının adresi (GitHub Raw URL veya misteknoloji360.com.tr üzerindeki adres).', 'ai-content-seo-assistant'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Güncelleme Kontrolü', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <button type="button" id="ai-btn-check-updates-now" class="button button-secondary">
                                    <span class="dashicons dashicons-update ai-spin-icon" style="display:none; vertical-align:text-bottom; margin-right:4px;"></span>
                                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('Şimdi Güncellemeleri Denetle', 'ai-content-seo-assistant'); ?>
                                </button>
                                <div id="ai-update-check-status" style="margin-top:12px; font-weight:600; font-size:14px;"></div>
                            </td>
                        </tr>
                    </table>

                    <div style="background:#f0f6fc; border-left:4px solid #2271b1; padding:15px 20px; border-radius:4px; margin-top:20px;">
                        <h4 style="margin:0 0 8px 0; color:#1d2327;">💡 Güncelleme Nasıl Yayınlanır?</h4>
                        <ol style="margin:0; padding-left:20px; font-size:13px; line-height:1.7; color:#50575e;">
                            <li>Eklentide yeni bir değişiklik yaptığınızda sürüm numarasını <code>update-info.json</code> içinde yükseltin (Örn: <code>1.0.1</code>).</li>
                            <li>Yeni <code>ai-content-seo-assistant.zip</code> dosyasını ve <code>update-info.json</code> dosyasını GitHub reponuza (veya web sitenize) yükleyin.</li>
                            <li>Eklentinin kurulu olduğu diğer tüm 3-4 WordPress siteniz, arka planda yeni sürümü otomatik algılar ve WordPress'in standart <strong>"Eklentiler &rarr; Yeni bir sürüm mevcut. Şimdi Güncelle"</strong> butonunu açar!</li>
                        </ol>
                    </div>
                </div>

                <!-- 6. TAB: LİSANS VE AKTİVASYON -->
                <div class="ai-seo-admin-tab-pane <?php echo ($active_tab === 'license') ? 'active' : ''; ?>" id="tab-pane-license" style="<?php echo ($active_tab === 'license') ? 'display:block;' : 'display:none;'; ?>">
                    <?php
                    $lic_info = class_exists('AI_SEO_License_Manager') ? AI_SEO_License_Manager::get_license_info() : array('is_active' => false);
                    $is_active = !empty($lic_info['is_active']);
                    ?>
                    <div class="ai-seo-card <?php echo $is_active ? 'ai-seo-card-primary' : ''; ?>" style="margin-bottom:20px; <?php echo !$is_active ? 'border-left:4px solid #d63638; background:#fff8f7;' : ''; ?>">
                        <h3 style="margin-top:0;">
                            <?php if ($is_active): ?>
                                <span class="dashicons dashicons-yes-alt" style="color:#1e7e34; font-size:24px; vertical-align:text-bottom;"></span>
                                <span style="color:#1e7e34;"><?php esc_html_e('Eklenti Lisansı Aktif & Doğrulandı', 'ai-content-seo-assistant'); ?></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color:#d63638; font-size:24px; vertical-align:text-bottom;"></span>
                                <span style="color:#d63638;"><?php esc_html_e('Eklenti Lisansı Etkin Değil', 'ai-content-seo-assistant'); ?></span>
                            <?php endif; ?>
                        </h3>
                        <p style="font-size:14px; line-height:1.6; color:#3c434a; margin-bottom:0;">
                            <?php if ($is_active): ?>
                                <?php printf(esc_html__('Bu site (%s) için %s lisansı başarıyla etkinleştirildi. Tüm yapay zeka özellikleri ve otomatik pilot sınırsız kullanıma açıktır.', 'ai-content-seo-assistant'), '<strong>' . esc_html($lic_info['domain']) . '</strong>', '<strong>' . esc_html($lic_info['type']) . '</strong>'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Yapay zeka içerik üretimi ve otomatik pilot özelliklerini kullanabilmek için lütfen misteknoloji360.com.tr tarafından sağlanan geçerli bir lisans anahtarı girin.', 'ai-content-seo-assistant'); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <table class="form-table ai-seo-form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Lisans Durumu', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="ai-badge ai-badge-popular" style="background:#1e7e34; color:#fff; font-size:13px; padding:4px 10px;">🟢 <?php echo esc_html($lic_info['type']); ?></span>
                                    <span style="margin-left:10px; color:#50575e; font-size:13px;">(Aktivasyon: <?php echo esc_html($lic_info['activated_at']); ?>)</span>
                                <?php else: ?>
                                    <span class="ai-badge" style="background:#d63638; color:#fff; font-size:13px; padding:4px 10px;">🔴 <?php esc_html_e('Lisanssız / Beklemede', 'ai-content-seo-assistant'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Lisanslı Alan Adı (Domain)', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <code><?php echo esc_html(AI_SEO_License_Manager::get_current_domain()); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Geliştirici & Üretici', 'ai-content-seo-assistant'); ?></th>
                            <td>
                                <strong>Serkan AKKAYA</strong> &mdash; <a href="https://misteknoloji360.com.tr/" target="_blank">misteknoloji360.com.tr &rarr;</a>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_license_key_input"><?php esc_html_e('Lisans Anahtarı', 'ai-content-seo-assistant'); ?></label></th>
                            <td>
                                <input type="text" id="ai_license_key_input" class="regular-text" style="font-family:monospace; font-size:14px; letter-spacing:1px;" placeholder="MIS-PRO-XXXX-XXXX" value="<?php echo esc_attr($lic_info['key'] ?? ''); ?>" <?php echo $is_active ? 'readonly' : ''; ?> />
                                
                                <div style="margin-top:12px;">
                                    <?php if (!$is_active): ?>
                                        <button type="button" id="ai-btn-activate-license" class="button button-primary button-large">
                                            <span class="dashicons dashicons-update ai-spin-icon" style="display:none; vertical-align:text-bottom; margin-right:4px;"></span>
                                            <span class="dashicons dashicons-unlock"></span> <?php esc_html_e('Lisansı Şimdi Etkinleştir', 'ai-content-seo-assistant'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" id="ai-btn-deactivate-license" class="button button-secondary" style="color:#d63638;">
                                            <span class="dashicons dashicons-update ai-spin-icon" style="display:none; vertical-align:text-bottom; margin-right:4px;"></span>
                                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Lisansı Bu Siteden Kaldır', 'ai-content-seo-assistant'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div id="ai-license-ajax-status" style="margin-top:12px; font-weight:600; font-size:14px;"></div>
                            </td>
                        </tr>
                    </table>

                    <div style="background:#fff; border:1px solid #c3c4c7; padding:15px 20px; border-radius:4px; margin-top:20px;">
                        <h4 style="margin:0 0 8px 0; color:#1d2327;">🛡️ Geliştirici Master Lisans Anahtarları</h4>
                        <p style="margin:0 0 10px 0; font-size:13px; color:#50575e;">
                            Kendi siteleriniz veya test ortamlarınız için aşağıdaki Master Anahtarı doğrudan girebilirsiniz:
                        </p>
                        <code style="background:#f0f0f1; padding:6px 12px; font-size:14px; font-weight:bold; color:#135e96; border-radius:3px; display:inline-block;">MIS-MASTER-360-SERKAN-AKKAYA</code>
                    </div>
                </div>

                <p class="submit">
                    <?php submit_button(__('Ayarları Kaydet', 'ai-content-seo-assistant'), 'primary', 'submit', false); ?>
                </p>
            </form>
        </div>
        <?php
    }
}
