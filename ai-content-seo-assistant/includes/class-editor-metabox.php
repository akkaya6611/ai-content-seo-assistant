<?php
/**
 * Gutenberg ve Classic Editör Meta Box Arayüzü
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Editor_Metabox {

    private $options;

    public function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('add_meta_boxes', array($this, 'add_editor_metabox'));
        add_action('save_post', array($this, 'save_metabox_data'));
    }

    /**
     * Meta Box'ı Ekle
     */
    public function add_editor_metabox() {
        $post_types = $this->options['post_types'] ?? array('post', 'page');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'ai_seo_assistant_metabox',
                __('⚡ AI İçerik & SEO Asistanı', 'ai-content-seo-assistant'),
                array($this, 'render_metabox_content'),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Meta Box HTML İçeriği
     */
    public function render_metabox_content($post) {
        wp_nonce_field('ai_seo_save_meta_box', 'ai_seo_metabox_nonce');

        $meta_title = get_post_meta($post->ID, '_ai_seo_meta_title', true);
        $meta_desc = get_post_meta($post->ID, '_ai_seo_meta_desc', true);
        $focus_keyword = get_post_meta($post->ID, '_ai_seo_focus_keyword', true);
        $default_provider = $this->options['default_provider'] ?? 'openrouter';
        $site_name = get_bloginfo('name');
        $is_licensed = class_exists('AI_SEO_License_Manager') ? AI_SEO_License_Manager::is_licensed() : false;
        ?>
        <div class="ai-seo-panel-container" id="ai-seo-panel-app">

            <?php if (!$is_licensed): ?>
                <div style="background:#fef2f2; border:1px solid #f87171; border-radius:8px; padding:12px 16px; margin-bottom:15px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-lock" style="font-size:24px; width:24px; height:24px; color:#ef4444;"></span>
                        <div>
                            <strong style="color:#991b1b; font-size:13px; display:block;"><?php esc_html_e('Eklenti Lisansı Etkin Değil', 'ai-content-seo-assistant'); ?></strong>
                            <span style="color:#b91c1c; font-size:12px;"><?php esc_html_e('Yapay zeka ile makale, iyileştirme ve SEO meta üretimi yapabilmek için lisansınızı etkinleştirin.', 'ai-content-seo-assistant'); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=ai-content-seo-assistant-license'); ?>" target="_blank" class="button button-primary" style="background:#ef4444; border-color:#dc2626; font-weight:600; white-space:nowrap;">
                        🔑 <?php esc_html_e('Lisansı Etkinleştir', 'ai-content-seo-assistant'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Sekme Başlıkları -->
            <div class="ai-seo-tabs-nav">
                <button type="button" class="ai-seo-tab-btn active" data-tab="ai-generator">
                    <span class="dashicons dashicons-edit-page"></span> <?php esc_html_e('AI İçerik Üretici', 'ai-content-seo-assistant'); ?>
                </button>
                <button type="button" class="ai-seo-tab-btn" data-tab="ai-rephrase">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Metin İyileştirici', 'ai-content-seo-assistant'); ?>
                </button>
                <button type="button" class="ai-seo-tab-btn" data-tab="ai-seo">
                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('SEO & SERP Önizleme', 'ai-content-seo-assistant'); ?>
                </button>
            </div>

            <!-- 1. SEKME: İÇERİK ÜRETİCİ -->
            <div class="ai-seo-tab-content active" id="tab-ai-generator">
                <div class="ai-seo-row">
                    <div class="ai-seo-col ai-seo-col-form">
                        <div class="ai-form-group">
                            <label for="ai_gen_type"><?php esc_html_e('Ne Üretmek İstiyorsunuz?', 'ai-content-seo-assistant'); ?></label>
                            <select id="ai_gen_type" class="ai-input">
                                <option value="article"><?php esc_html_e('Tam Kapsamlı Makale (H2, H3 Başlıklı)', 'ai-content-seo-assistant'); ?></option>
                                <option value="outline"><?php esc_html_e('Makale Taslağı / İçerik Planı (Outline)', 'ai-content-seo-assistant'); ?></option>
                                <option value="titles"><?php esc_html_e('5 Farklı Dikkat Çekici Başlık Önerisi', 'ai-content-seo-assistant'); ?></option>
                                <option value="intro"><?php esc_html_e('Giriş Paragrafı (Kanca / Hook)', 'ai-content-seo-assistant'); ?></option>
                                <option value="conclusion"><?php esc_html_e('Sonuç Paragrafı & Çağrı (Call to Action)', 'ai-content-seo-assistant'); ?></option>
                                <option value="faq"><?php esc_html_e('Sıkça Sorulan Sorular (SSS / FAQ)', 'ai-content-seo-assistant'); ?></option>
                            </select>
                        </div>

                        <div class="ai-form-group">
                            <label for="ai_gen_topic"><?php esc_html_e('Konu veya Ana Fikir:', 'ai-content-seo-assistant'); ?></label>
                            <input type="text" id="ai_gen_topic" class="ai-input" placeholder="<?php esc_attr_e('Örn: WordPress Hızlandırma Yöntemleri ve Önbellek İpuçları', 'ai-content-seo-assistant'); ?>" value="<?php echo esc_attr(get_the_title($post->ID)); ?>" />
                        </div>

                        <div class="ai-form-row">
                            <div class="ai-form-group ai-form-col-6">
                                <label for="ai_gen_keywords"><?php esc_html_e('Anahtar Kelimeler (Virgülle):', 'ai-content-seo-assistant'); ?></label>
                                <input type="text" id="ai_gen_keywords" class="ai-input" placeholder="yer sofrası, ahşap masa, katlanır sofra" value="<?php echo esc_attr($focus_keyword); ?>" />
                            </div>
                            <div class="ai-form-group ai-form-col-6">
                                <label for="ai_gen_tone"><?php esc_html_e('Yazım Tonu:', 'ai-content-seo-assistant'); ?></label>
                                <select id="ai_gen_tone" class="ai-input">
                                    <option value="professional"><?php esc_html_e('Profesyonel & Kurumsal', 'ai-content-seo-assistant'); ?></option>
                                    <option value="friendly"><?php esc_html_e('Samimi & Akıcı', 'ai-content-seo-assistant'); ?></option>
                                    <option value="academic"><?php esc_html_e('Bilgilendirici & Rehber', 'ai-content-seo-assistant'); ?></option>
                                    <option value="persuasive"><?php esc_html_e('Pazarlama & İkna Edici / Satış', 'ai-content-seo-assistant'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="ai-form-row">
                            <div class="ai-form-group ai-form-col-12" style="flex:1;">
                                <label for="ai_gen_provider"><?php esc_html_e('Kullanılacak Yapay Zeka Modeli:', 'ai-content-seo-assistant'); ?></label>
                                <select id="ai_gen_provider" class="ai-input">
                                    <option value="groq" <?php selected($default_provider, 'groq'); ?>>Groq (Llama 3.3 70B & DeepSeek R1 - Ultra Hızlı)</option>
                                    <option value="gemini" <?php selected($default_provider, 'gemini'); ?>>Google Gemini (gemini-2.5-flash - Çok Hızlı & Ücretsiz Kotası Yüksek)</option>
                                    <option value="deepseek" <?php selected($default_provider, 'deepseek'); ?>>DeepSeek (deepseek-chat V3 - Ekonomik & Zeki)</option>
                                    <option value="openrouter" <?php selected($default_provider, 'openrouter'); ?>>OpenRouter (Gemini 2.0 / Llama 3.3 / Qwen)</option>
                                    <option value="anthropic" <?php selected($default_provider, 'anthropic'); ?>>Anthropic Claude (3.7 Sonnet / 3.5 Haiku)</option>
                                    <option value="openai" <?php selected($default_provider, 'openai'); ?>>OpenAI (gpt-4o-mini / gpt-4o / o3-mini)</option>
                                    <option value="custom" <?php selected($default_provider, 'custom'); ?>>Özel Uç Nokta (Z.ai, MiniMax, Yerel LLM)</option>
                                </select>
                            </div>
                        </div>

                        <div class="ai-form-actions">
                            <button type="button" id="ai-btn-generate-content" class="button button-primary ai-action-btn">
                                <span class="dashicons dashicons-admin-generic ai-spin-icon" style="display:none;"></span>
                                <span class="dashicons dashicons-superhero"></span> <?php esc_html_e('Yapay Zeka ile Üret', 'ai-content-seo-assistant'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="ai-seo-col ai-seo-col-preview">
                        <div class="ai-preview-header">
                            <h4><?php esc_html_e('Üretilen İçerik', 'ai-content-seo-assistant'); ?></h4>
                            <div class="ai-preview-actions">
                                <button type="button" id="ai-btn-copy-output" class="button button-small" title="<?php esc_attr_e('Panoya Kopyala', 'ai-content-seo-assistant'); ?>">
                                    <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Kopyala', 'ai-content-seo-assistant'); ?>
                                </button>
                                <button type="button" id="ai-btn-insert-editor" class="button button-small button-primary" title="<?php esc_attr_e('Editöre Ekle', 'ai-content-seo-assistant'); ?>">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Editöre Aktar', 'ai-content-seo-assistant'); ?>
                                </button>
                            </div>
                        </div>
                        <div class="ai-output-box" id="ai-content-output-box" contenteditable="true" data-placeholder="<?php esc_attr_e('Yapay zekanın ürettiği içerik burada görüntülenecek...', 'ai-content-seo-assistant'); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- 2. SEKME: METİN İYİLEŞTİRİCİ -->
            <div class="ai-seo-tab-content" id="tab-ai-rephrase">
                <div class="ai-seo-row">
                    <div class="ai-seo-col ai-seo-col-6">
                        <div class="ai-form-group">
                            <label for="ai_rephrase_input"><?php esc_html_e('İyileştirilecek / Değiştirilecek Metin:', 'ai-content-seo-assistant'); ?></label>
                            <textarea id="ai_rephrase_input" class="ai-textarea" rows="7" placeholder="<?php esc_attr_e('Yeniden yazmak, genişletmek veya özetlemek istediğiniz metni buraya yapıştırın...', 'ai-content-seo-assistant'); ?>"></textarea>
                        </div>
                        <div class="ai-rephrase-actions">
                            <button type="button" class="button ai-rephrase-action-btn" data-action="rephrase">
                                <span class="dashicons dashicons-randomize"></span> <?php esc_html_e('Yeniden Yaz (Paraphrase)', 'ai-content-seo-assistant'); ?>
                            </button>
                            <button type="button" class="button ai-rephrase-action-btn" data-action="expand">
                                <span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e('Detaylandır & Genişlet', 'ai-content-seo-assistant'); ?>
                            </button>
                            <button type="button" class="button ai-rephrase-action-btn" data-action="summarize">
                                <span class="dashicons dashicons-editor-contract"></span> <?php esc_html_e('Özet Çıkar', 'ai-content-seo-assistant'); ?>
                            </button>
                            <button type="button" class="button ai-rephrase-action-btn" data-action="grammar">
                                <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Dilbilgisi & İmla Düzelt', 'ai-content-seo-assistant'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="ai-seo-col ai-seo-col-6">
                        <div class="ai-preview-header">
                            <h4><?php esc_html_e('İyileştirilmiş Sonuç', 'ai-content-seo-assistant'); ?></h4>
                            <button type="button" id="ai-btn-insert-rephrase" class="button button-small button-primary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Editöre Ekle', 'ai-content-seo-assistant'); ?>
                            </button>
                        </div>
                        <div class="ai-output-box" id="ai-rephrase-output-box" contenteditable="true" data-placeholder="<?php esc_attr_e('İyileştirilmiş metin burada görüntülenecek...', 'ai-content-seo-assistant'); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- 3. SEKME: SEO & CANLI SERP ÖNİZLEME -->
            <div class="ai-seo-tab-content" id="tab-ai-seo">
                <div class="ai-seo-row">
                    <div class="ai-seo-col ai-seo-col-6">
                        
                        <!-- Odak Anahtar Kelime -->
                        <div class="ai-form-group">
                            <label for="ai_seo_focus_keyword"><?php esc_html_e('Odak Anahtar Kelime (Focus Keyword):', 'ai-content-seo-assistant'); ?></label>
                            <input type="text" id="ai_seo_focus_keyword" name="ai_seo_focus_keyword" class="ai-input" value="<?php echo esc_attr($focus_keyword); ?>" placeholder="<?php esc_attr_e('Örn: wordpress hızlandırma', 'ai-content-seo-assistant'); ?>" />
                        </div>

                        <!-- SEO Başlığı -->
                        <div class="ai-form-group">
                            <div class="ai-field-label-row">
                                <label for="ai_seo_meta_title"><?php esc_html_e('SEO Meta Başlığı (Meta Title):', 'ai-content-seo-assistant'); ?></label>
                                <div class="ai-counter-wrap">
                                    <span id="ai-title-count">0</span> / 60 <?php esc_html_e('karakter', 'ai-content-seo-assistant'); ?>
                                    <span id="ai-title-badge" class="ai-length-badge"></span>
                                </div>
                            </div>
                            <div class="ai-input-with-btn">
                                <input type="text" id="ai_seo_meta_title" name="ai_seo_meta_title" class="ai-input" value="<?php echo esc_attr($meta_title); ?>" placeholder="<?php esc_attr_e('Google arama sonucunda görünecek başlık', 'ai-content-seo-assistant'); ?>" />
                                <button type="button" id="ai-btn-gen-title" class="button" title="<?php esc_attr_e('AI ile SEO Başlığı Üret', 'ai-content-seo-assistant'); ?>">
                                    <span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Üret', 'ai-content-seo-assistant'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- SEO Açıklaması -->
                        <div class="ai-form-group">
                            <div class="ai-field-label-row">
                                <label for="ai_seo_meta_desc"><?php esc_html_e('SEO Meta Açıklaması (Meta Description):', 'ai-content-seo-assistant'); ?></label>
                                <div class="ai-counter-wrap">
                                    <span id="ai-desc-count">0</span> / 160 <?php esc_html_e('karakter', 'ai-content-seo-assistant'); ?>
                                    <span id="ai-desc-badge" class="ai-length-badge"></span>
                                </div>
                            </div>
                            <div class="ai-input-with-btn">
                                <textarea id="ai_seo_meta_desc" name="ai_seo_meta_desc" class="ai-textarea" rows="3" placeholder="<?php esc_attr_e('Google arama sonucunda görünecek özet açıklama...', 'ai-content-seo-assistant'); ?>"><?php echo esc_textarea($meta_desc); ?></textarea>
                                <button type="button" id="ai-btn-gen-desc" class="button" title="<?php esc_attr_e('AI ile Meta Açıklama Üret', 'ai-content-seo-assistant'); ?>">
                                    <span class="dashicons dashicons-superhero"></span> <?php esc_html_e('AI Üret', 'ai-content-seo-assistant'); ?>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Canlı SERP Önizlemesi -->
                    <div class="ai-seo-col ai-seo-col-6">
                        <div class="ai-serp-preview-header">
                            <h4><?php esc_html_e('Google Canlı Arama Sonucu Önizlemesi', 'ai-content-seo-assistant'); ?></h4>
                            <div class="ai-serp-mode-toggle">
                                <button type="button" class="ai-serp-toggle-btn active" data-mode="desktop">
                                    <span class="dashicons dashicons-desktop"></span> <?php esc_html_e('Masaüstü', 'ai-content-seo-assistant'); ?>
                                </button>
                                <button type="button" class="ai-serp-toggle-btn" data-mode="mobile">
                                    <span class="dashicons dashicons-smartphone"></span> <?php esc_html_e('Mobil', 'ai-content-seo-assistant'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Gerçekçi Google SERP Kartı -->
                        <div class="ai-serp-box desktop-mode" id="ai-serp-box">
                            <div class="ai-serp-url-row">
                                <div class="ai-serp-favicon">
                                    <span class="dashicons dashicons-admin-site-alt3"></span>
                                </div>
                                <div class="ai-serp-url-details">
                                    <span class="ai-serp-site-name"><?php echo esc_html($site_name); ?></span>
                                    <span class="ai-serp-link" id="ai-serp-preview-url"><?php echo esc_html($sample_url); ?></span>
                                </div>
                            </div>
                            <h3 class="ai-serp-title" id="ai-serp-preview-title"><?php echo esc_html($meta_title ?: get_the_title($post->ID) ?: __('Örnek Sayfa Başlığı', 'ai-content-seo-assistant')); ?></h3>
                            <p class="ai-serp-desc" id="ai-serp-preview-desc"><?php echo esc_html($meta_desc ?: __('Sayfanızın arama motoru sonuçlarında görüntülenecek meta açıklaması burada canlı olarak simüle edilir.', 'ai-content-seo-assistant')); ?></p>
                        </div>

                        <!-- Hızlı SEO Puanı ve Denetim -->
                        <div class="ai-seo-checklist">
                            <h5><?php esc_html_e('SEO Kontrol Listesi', 'ai-content-seo-assistant'); ?></h5>
                            <ul class="ai-seo-checklist-items">
                                <li id="check-title-len"><span class="dashicons dashicons-marker"></span> <?php esc_html_e('SEO Başlık Uzunluğu (40-60 karakter)', 'ai-content-seo-assistant'); ?></li>
                                <li id="check-desc-len"><span class="dashicons dashicons-marker"></span> <?php esc_html_e('Meta Açıklama Uzunluğu (120-160 karakter)', 'ai-content-seo-assistant'); ?></li>
                                <li id="check-keyword-title"><span class="dashicons dashicons-marker"></span> <?php esc_html_e('Başlıkta Odak Anahtar Kelime', 'ai-content-seo-assistant'); ?></li>
                                <li id="check-keyword-desc"><span class="dashicons dashicons-marker"></span> <?php esc_html_e('Açıklamada Odak Anahtar Kelime', 'ai-content-seo-assistant'); ?></li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Post Kaydedildiğinde Meta Box Verilerini Kaydet
     */
    public function save_metabox_data($post_id) {
        // Güvenlik & Yetki Kontrolleri
        if (!isset($_POST['ai_seo_metabox_nonce']) || !wp_verify_nonce($_POST['ai_seo_metabox_nonce'], 'ai_seo_save_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Meta Title
        if (isset($_POST['ai_seo_meta_title'])) {
            update_post_meta($post_id, '_ai_seo_meta_title', sanitize_text_field($_POST['ai_seo_meta_title']));
        }

        // Meta Description
        if (isset($_POST['ai_seo_meta_desc'])) {
            update_post_meta($post_id, '_ai_seo_meta_desc', sanitize_textarea_field($_POST['ai_seo_meta_desc']));
        }

        // Focus Keyword
        if (isset($_POST['ai_seo_focus_keyword'])) {
            update_post_meta($post_id, '_ai_seo_focus_keyword', sanitize_text_field($_POST['ai_seo_focus_keyword']));
        }
    }
}
