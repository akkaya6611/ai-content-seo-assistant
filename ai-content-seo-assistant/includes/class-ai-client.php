<?php
/**
 * Çoklu Yapay Zeka Sağlayıcısı İstemcisi
 * OpenAI, Anthropic, Gemini, DeepSeek, OpenRouter ve Özel Endpoint (Z.ai, MiniMax, Local LLM) desteği
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_Client {

    /**
     * Eklenti ayarları
     */
    private $options;

    public function __construct() {
        $this->options = get_option('ai_seo_assistant_options', array());
    }

    /**
     * Sağlayıcıya göre metin tamamlama/üretim isteği gönderir
     *
     * @param string $prompt       Kullanıcı istemi
     * @param string $system_prompt Sistem rolü / kuralı
     * @param array  $custom_opts   Özel seçenekler (provider, model, temperature, max_tokens, key, base_url)
     * @return array                ['success' => bool, 'content' => string, 'error' => string]
     */
    public function generate($prompt, $system_prompt = '', $custom_opts = array()) {
        // Lisans Kontrolü (Test aramaları hariç)
        $is_test = !empty($custom_opts['is_test']);
        if (!$is_test && class_exists('AI_SEO_License_Manager') && !AI_SEO_License_Manager::is_licensed()) {
            return array(
                'success' => false,
                'error'   => __('Eklenti lisansı etkin değil! Lütfen Ayarlar > AI SEO Asistanı > 🔑 Lisans sekmesinden geçerli bir lisans anahtarı girin (misteknoloji360.com.tr).', 'ai-content-seo-assistant'),
            );
        }

        $primary_provider = !empty($custom_opts['provider']) ? sanitize_text_field($custom_opts['provider']) : ($this->options['default_provider'] ?? 'gemini');
        $temperature = isset($custom_opts['temperature']) ? floatval($custom_opts['temperature']) : floatval($this->options['temperature'] ?? 0.7);
        $max_tokens = isset($custom_opts['max_tokens']) ? intval($custom_opts['max_tokens']) : intval($this->options['max_tokens'] ?? 2000);
        $override_key = !empty($custom_opts['key']) ? trim($custom_opts['key']) : null;
        $override_model = !empty($custom_opts['model']) ? trim($custom_opts['model']) : null;
        $override_base_url = !empty($custom_opts['base_url']) ? trim($custom_opts['base_url']) : null;

        // Test araması ise sadece o sağlayıcıyı tek başına dene
        if ($is_test) {
            return $this->execute_provider_call($primary_provider, $prompt, $system_prompt, $override_model, $override_key, $override_base_url, $temperature, $max_tokens);
        }

        // 1. Birincil Seçilen Sağlayıcıyı Çağır
        $res = $this->execute_provider_call($primary_provider, $prompt, $system_prompt, $override_model, $override_key, $override_base_url, $temperature, $max_tokens);
        if ($res['success']) {
            return $res;
        }

        // Eğer limit dolduysa (429, quota, rate limit, limit vb.) veya servis hatası varsa:
        // Diğer tanımlı API anahtarlarına sahip sağlayıcıları sırayla otomatik devreye sok!
        $all_providers = array('gemini', 'groq', 'openrouter', 'deepseek', 'openai', 'anthropic', 'custom');
        foreach ($all_providers as $fallback_provider) {
            if ($fallback_provider === $primary_provider) {
                continue;
            }

            // Eğer bu sağlayıcının API anahtarı girilmişse otomatik dene
            if ($this->has_api_key_configured($fallback_provider)) {
                $fb_res = $this->execute_provider_call($fallback_provider, $prompt, $system_prompt, null, null, null, $temperature, $max_tokens);
                if ($fb_res['success']) {
                    return $fb_res;
                }
            }
        }

        return $res;
    }

    /**
     * Sağlayıcının API Anahtarı Tanımlı mı?
     */
    private function has_api_key_configured($provider) {
        switch ($provider) {
            case 'gemini':
                return !empty(trim($this->options['gemini_key'] ?? ''));
            case 'groq':
                return !empty(trim($this->options['groq_key'] ?? ''));
            case 'deepseek':
                return !empty(trim($this->options['deepseek_key'] ?? ''));
            case 'openrouter':
                return !empty(trim($this->options['openrouter_key'] ?? ''));
            case 'openai':
                return !empty(trim($this->options['openai_key'] ?? ''));
            case 'anthropic':
                return !empty(trim($this->options['anthropic_key'] ?? ''));
            case 'custom':
                return !empty(trim($this->options['custom_key'] ?? ''));
            default:
                return false;
        }
    }

    /**
     * Belirtilen Sağlayıcıyı Çağır
     */
    private function execute_provider_call($provider, $prompt, $system_prompt, $override_model, $override_key, $override_base_url, $temperature, $max_tokens) {
        switch ($provider) {
            case 'groq':
                return $this->call_groq($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);

            case 'openai':
                return $this->call_openai($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);

            case 'anthropic':
                return $this->call_anthropic($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);

            case 'gemini':
                return $this->call_gemini($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);

            case 'deepseek':
                return $this->call_deepseek($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);

            case 'custom':
                return $this->call_custom($prompt, $system_prompt, $override_model, $override_key, $override_base_url, $temperature, $max_tokens);

            case 'openrouter':
            default:
                return $this->call_openrouter($prompt, $system_prompt, $override_model, $override_key, $temperature, $max_tokens);
        }
    }

    /**
     * Groq API çağrısı (Ultra Hızlı Llama 3.1 / 3.3, DeepSeek R1 Distill)
     */
    private function call_groq($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['groq_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('Groq API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['groq_model'] ?? 'llama-3.3-70b-versatile');
        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array('role' => 'system', 'content' => $system_prompt);
        }
        $messages[] = array('role' => 'user', 'content' => $prompt);

        $body = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        );

        $res = $this->post_json('https://api.groq.com/openai/v1/chat/completions', array(
            'Authorization' => 'Bearer ' . $api_key,
        ), $body, function($json) {
            return $json['choices'][0]['message']['content'] ?? '';
        });

        // Eğer seçilen model Groq'ta 400 (decommissioned/deprecated) veya 404 (bulunamadı) dönerse, resmi aktif modeller ile otomatik tekrar dene
        if (!$res['success'] && (strpos($res['error'], '400') !== false || strpos($res['error'], '404') !== false || strpos($res['error'], 'decommissioned') !== false || strpos($res['error'], 'deprecated') !== false || strpos($res['error'], 'does not exist') !== false || strpos($res['error'], 'not found') !== false)) {
            $fallbacks = array('llama-3.3-70b-versatile', 'openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3.6-27b', 'deepseek-r1-distill-llama-70b');
            foreach ($fallbacks as $fb_model) {
                if ($fb_model === $model) {
                    continue;
                }
                $body['model'] = $fb_model;
                $res = $this->post_json('https://api.groq.com/openai/v1/chat/completions', array(
                    'Authorization' => 'Bearer ' . $api_key,
                ), $body, function($json) {
                    return $json['choices'][0]['message']['content'] ?? '';
                });
                if ($res['success']) {
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * OpenAI API çağrısı (GPT-4o, GPT-4o-mini, o1, o3-mini)
     */
    private function call_openai($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['openai_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('OpenAI API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['openai_model'] ?? 'gpt-4o-mini');
        $messages = array();

        // o1 ve o3-mini muhakeme modelleri özel parametreler gerektirir (developer rolü ve max_completion_tokens)
        $is_reasoning = (strpos($model, 'o1') === 0 || strpos($model, 'o3') === 0);

        if ($is_reasoning) {
            if (!empty($system_prompt)) {
                $messages[] = array('role' => 'developer', 'content' => $system_prompt);
            }
            $messages[] = array('role' => 'user', 'content' => $prompt);

            $body = array(
                'model'                 => $model,
                'messages'              => $messages,
                'max_completion_tokens' => $max_tokens,
            );
        } else {
            if (!empty($system_prompt)) {
                $messages[] = array('role' => 'system', 'content' => $system_prompt);
            }
            $messages[] = array('role' => 'user', 'content' => $prompt);

            $body = array(
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $max_tokens,
            );
        }

        return $this->post_json('https://api.openai.com/v1/chat/completions', array(
            'Authorization' => 'Bearer ' . $api_key,
        ), $body, function($json) {
            return $json['choices'][0]['message']['content'] ?? '';
        });
    }

    /**
     * Anthropic Claude API çağrısı
     */
    private function call_anthropic($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['anthropic_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('Anthropic API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['anthropic_model'] ?? 'claude-3-7-sonnet-20250219');
        $body = array(
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => array(
                array('role' => 'user', 'content' => $prompt)
            ),
        );
        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        return $this->post_json('https://api.anthropic.com/v1/messages', array(
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ), $body, function($json) {
            if (!empty($json['content'][0]['text'])) {
                return $json['content'][0]['text'];
            }
            return '';
        });
    }

    /**
     * Google Gemini API çağrısı (Gemini 3.6 Flash / 2.5 Flash)
     */
    private function call_gemini($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['gemini_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('Google Gemini API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['gemini_model'] ?? 'gemini-3.6-flash');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($api_key);

        $body = array(
            'contents' => array(
                array(
                    'role'  => 'user',
                    'parts' => array(array('text' => $prompt))
                )
            ),
            'generationConfig' => array(
                'temperature'     => $temperature,
                'maxOutputTokens' => $max_tokens,
                'thinkingConfig'  => array(
                    'thinkingBudget' => 0,
                ),
            )
        );

        if (!empty($system_prompt)) {
            $body['systemInstruction'] = array(
                'parts' => array(array('text' => $system_prompt))
            );
        }

        $gemini_extractor = function($json) {
            if (!empty($json['candidates'][0]['content']['parts'])) {
                $text = '';
                foreach ($json['candidates'][0]['content']['parts'] as $part) {
                    if (!empty($part['thought'])) {
                        continue;
                    }
                    $text .= $part['text'] ?? '';
                }
                return $text;
            }
            return $json['candidates'][0]['text'] ?? '';
        };

        $res = $this->post_json($url, array(), $body, $gemini_extractor);

        // Eğer 404/400 (model bulunamadı veya no longer available) dönerse, sırayla güncel aktif modellerle otomatik tekrar dene
        if (!$res['success'] && (strpos($res['error'], '404') !== false || strpos($res['error'], '400') !== false || strpos($res['error'], 'not found') !== false || strpos($res['error'], 'no longer available') !== false)) {
            $fallbacks = array('gemini-3.6-flash', 'gemini-3.1-pro-preview', 'gemini-2.5-flash');
            foreach ($fallbacks as $fb) {
                if ($fb === $model) continue;
                $fallback_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($fb) . ':generateContent?key=' . urlencode($api_key);
                $res = $this->post_json($fallback_url, array(), $body, $gemini_extractor);
                if ($res['success']) {
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * DeepSeek API çağrısı (DeepSeek V3 / R1)
     */
    private function call_deepseek($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['deepseek_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('DeepSeek API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['deepseek_model'] ?? 'deepseek-chat');
        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array('role' => 'system', 'content' => $system_prompt);
        }
        $messages[] = array('role' => 'user', 'content' => $prompt);

        $body = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        );

        return $this->post_json('https://api.deepseek.com/chat/completions', array(
            'Authorization' => 'Bearer ' . $api_key,
        ), $body, function($json) {
            if (!empty($json['choices'][0]['message']['content'])) {
                return $json['choices'][0]['message']['content'];
            }
            if (!empty($json['choices'][0]['message']['reasoning_content'])) {
                return $json['choices'][0]['message']['reasoning_content'];
            }
            return '';
        });
    }

    /**
     * OpenRouter API çağrısı
     */
    private function call_openrouter($prompt, $system_prompt, $model, $key, $temperature, $max_tokens) {
        $api_key = $key ?: trim($this->options['openrouter_key'] ?? '');
        if (empty($api_key)) {
            return array('success' => false, 'error' => __('OpenRouter API anahtarı girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $model = $model ?: ($this->options['openrouter_model'] ?? 'google/gemini-2.0-flash-exp:free');
        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array('role' => 'system', 'content' => $system_prompt);
        }
        $messages[] = array('role' => 'user', 'content' => $prompt);

        $body = array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        );

        $res = $this->post_json('https://openrouter.ai/api/v1/chat/completions', array(
            'Authorization' => 'Bearer ' . $api_key,
            'HTTP-Referer'  => home_url(),
            'X-Title'       => get_bloginfo('name') . ' AI Assistant',
        ), $body, function($json) {
            return $json['choices'][0]['message']['content'] ?? '';
        });

        // Eğer OpenRouter 404 dönerse, openrouter/auto ile tekrar dene
        if (!$res['success'] && (strpos($res['error'], '404') !== false || strpos($res['error'], 'does not exist') !== false) && $model !== 'openrouter/auto') {
            $body['model'] = 'openrouter/auto';
            $res = $this->post_json('https://openrouter.ai/api/v1/chat/completions', array(
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo('name') . ' AI Assistant',
            ), $body, function($json) {
                return $json['choices'][0]['message']['content'] ?? '';
            });
        }

        return $res;
    }

    /**
     * Özel Endpoint (Z.ai, MiniMax, Yerel Ollama, LM Studio vb.) çağrısı
     */
    private function call_custom($prompt, $system_prompt, $model, $key, $base_url, $temperature, $max_tokens) {
        $base_url = $base_url ?: trim($this->options['custom_base_url'] ?? '');
        $api_key = $key ?: trim($this->options['custom_key'] ?? '');
        $model = $model ?: trim($this->options['custom_model'] ?? 'default');

        if (empty($base_url)) {
            return array('success' => false, 'error' => __('Özel API Base URL girilmemiş. Lütfen eklenti ayarlarını kontrol edin.', 'ai-content-seo-assistant'));
        }

        $parsed_url = wp_parse_url($base_url);
        if (!$parsed_url || !in_array($parsed_url['scheme'] ?? '', array('http', 'https'), true)) {
            return array('success' => false, 'error' => __('Geçersiz API Base URL protokolü. Sadece http:// veya https:// kullanılabilir.', 'ai-content-seo-assistant'));
        }

        $endpoint = rtrim($base_url, '/');
        $is_anthropic_style = (strpos($endpoint, 'anthropic.com') !== false || preg_match('/\/messages$/', $endpoint));

        if ($is_anthropic_style) {
            // Anthropic Messages formatı
            if (!preg_match('/\/messages$/', $endpoint)) {
                $endpoint .= '/v1/messages';
            }

            $body = array(
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'messages'   => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
            );
            if (!empty($system_prompt)) {
                $body['system'] = $system_prompt;
            }

            $headers = array(
                'x-api-key'         => $api_key,
                'Authorization'     => 'Bearer ' . $api_key,
                'anthropic-version' => '2023-06-01',
            );

            return $this->post_json($endpoint, $headers, $body, function($json) {
                return $json['content'][0]['text'] ?? '';
            });
        } else {
            // OpenAI Uyumlu Chat Completions formatı (Z.ai, MiniMax, Ollama, LM Studio, vLLM vb.)
            if (!preg_match('/\/chat\/completions$/', $endpoint)) {
                if (preg_match('/\/v1$/', $endpoint) || preg_match('/\/paas\/v4$/', $endpoint)) {
                    $endpoint .= '/chat/completions';
                } else {
                    $endpoint .= '/v1/chat/completions';
                }
            }

            $messages = array();
            if (!empty($system_prompt)) {
                $messages[] = array('role' => 'system', 'content' => $system_prompt);
            }
            $messages[] = array('role' => 'user', 'content' => $prompt);

            $body = array(
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $max_tokens,
            );

            $headers = array();
            if (!empty($api_key)) {
                $headers['Authorization'] = 'Bearer ' . $api_key;
            }

            return $this->post_json($endpoint, $headers, $body, function($json) {
                if (!empty($json['choices'][0]['message']['content'])) {
                    return $json['choices'][0]['message']['content'];
                }
                if (!empty($json['choices'][0]['text'])) {
                    return $json['choices'][0]['text'];
                }
                if (!empty($json['content'][0]['text'])) {
                    return $json['content'][0]['text'];
                }
                if (!empty($json['response'])) {
                    return $json['response'];
                }
                return '';
            });
        }
    }

    /**
     * Güvenli JSON POST isteği gönderir ve yanıtı işler
     */
    private function post_json($url, $headers, $body, $extractor) {
        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'WordPress AI-Content-SEO-Assistant/' . AI_SEO_VERSION;

        $args = array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => wp_json_encode($body),
            'timeout'     => 45,
            'data_format' => 'body',
            'sslverify'   => apply_filters('ai_seo_sslverify', false),
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            // SSL veya sunucu hatası varsa son çare sslverify=false ile tekrar dene
            $args['sslverify'] = false;
            $response = wp_remote_post($url, $args);
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error'   => sprintf(__('Sunucu bağlantı hatası: %s', 'ai-content-seo-assistant'), esc_html($response->get_error_message())),
                );
            }
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $json = json_decode($response_body, true);

        if ($status_code >= 400) {
            $error_message = '';
            if (!empty($json['error']['message'])) {
                $error_message = $json['error']['message'];
            } elseif (!empty($json['error']) && is_string($json['error'])) {
                $error_message = $json['error'];
            } elseif (!empty($json['message'])) {
                $error_message = $json['message'];
            } elseif (!empty($json['detail'])) {
                $error_message = is_string($json['detail']) ? $json['detail'] : json_encode($json['detail']);
            } elseif (!empty($response_body)) {
                $error_message = substr(strip_tags($response_body), 0, 300);
            } else {
                $error_message = sprintf(__('HTTP Hatası: %d', 'ai-content-seo-assistant'), $status_code);
            }

            $clean_error = wp_strip_all_tags((string)$error_message);

            return array(
                'success' => false,
                'error'   => sprintf(__('API Hatası (%d): %s', 'ai-content-seo-assistant'), $status_code, $clean_error),
            );
        }

        if (empty($json)) {
            return array(
                'success' => false,
                'error'   => __('Geçersiz API yanıtı alındı (JSON ayrıştırılamadı).', 'ai-content-seo-assistant'),
            );
        }

        // HTTP 200 ile dönen gizli API hata gövdelerini yakala
        if (!empty($json['error'])) {
            $err_msg = is_array($json['error']) ? ($json['error']['message'] ?? json_encode($json['error'])) : (string)$json['error'];
            return array(
                'success' => false,
                'error'   => sprintf(__('API Hatası: %s', 'ai-content-seo-assistant'), wp_strip_all_tags($err_msg)),
            );
        }

        if (!empty($json['promptFeedback']['blockReason'])) {
            return array(
                'success' => false,
                'error'   => sprintf(__('İçerik filtreye takıldı: %s', 'ai-content-seo-assistant'), $json['promptFeedback']['blockReason']),
            );
        }

        $content = call_user_func($extractor, $json);

        if (empty($content) || !is_string($content) || trim($content) === '') {
            return array(
                'success' => false,
                'error'   => __('Yapay zeka boş bir yanıt döndürdü. Lütfen API anahtarınızı ve model seçiminizi kontrol edin.', 'ai-content-seo-assistant'),
            );
        }

        $clean_content = $this->decontaminate_response($content);

        return array(
            'success' => true,
            'content' => $clean_content,
        );
    }

    /**
     * Yapay Zeka Çıktısını Arındırma & Temizleme (Düşünce Süreci, İngilizce Çeviri ve Dolgu Filtresi)
     */
    public function decontaminate_response($text) {
        if (!is_string($text) || empty(trim($text))) {
            return '';
        }

        $t = trim($text);

        // Baştaki ve sondaki tırnakları kaldır
        $t = preg_replace('/^["\'“”]/u', '', $t);
        $t = preg_replace('/["\'“”]$/u', '', $t);

        // 1. <think>...</think> etiketlerini ve içeriğini kaldır (DeepSeek R1 vb.)
        $t = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $t);

        // 2. Düşünce süreci sızıntılarını (Thinking process, analyze input, constraint vb.) satır satır filtrele
        if (preg_match('/(?:here[\'’]?s\s+a\s+|s\s+a\s+|\*?\*?)thinking\s+process|analyze\s+user\s+input|constraint\s+\d/iu', $t)) {
            $lines = preg_split('/\r?\n/', $t);
            $clean_lines = array();
            foreach ($lines as $line) {
                $trimmed_line = trim($line);
                if (empty($trimmed_line)) continue;
                if (!preg_match('/thinking\s+process|analyze\s+user\s+input|constraint\s+\d|user\s+input|role:\s*seo|goal:\s*create/iu', $trimmed_line)) {
                    $clean_lines[] = $line;
                }
            }
            if (!empty($clean_lines)) {
                $t = implode("\n\n", $clean_lines);
            } else {
                if (preg_match('/topic:?\s*[‘\'"*]*([^‘\'"*\r\n]{8,})/iu', $t, $mTopic)) {
                    $t = $mTopic[1];
                }
            }
        }

        // 3. Başta kalan düşünce bloğu artıkları varsa kes
        $t = preg_replace('/^(?:here[\'’]?s\s+a\s+|s\s+a\s+|\*?\*?)thinking\s+process[\s\S]*?(?:\n\n|\r\n\r\n|(?=<h[1-6]|<p|[A-ZÇĞİÖŞÜ]))/iu', '', $t);

        // 4. Markdown kod blokları başlıklarını temizle (```html ... ```)
        $t = preg_replace('/^```(?:html)?\s*/i', '', $t);
        $t = preg_replace('/\s*```$/', '', $t);

        // 5. Sohbet & Giriş Dolgularını temizle (Tabii ki, İşte hazırladığım, Sure, Here is...)
        $t = preg_replace('/^(tabii ki|elbette|işte|işte hazırladığım|sure|here is|certainly)[^\n\r<]*?:\s*(\r\n|\n)?/iu', '', $t);

        // 6. Parantez içindeki gereksiz İngilizce çevirileri temizle: (Style and Functionality in Small Apartments: Best Furniture...)
        $t = preg_replace('/\([A-Za-z\s,:\'’\-\.\?]{8,}\)/u', '', $t);

        // 7. Başlık ön eklerini ve tırnakları temizle
        $t = preg_replace('/^(?:başlık|seo başlığı|title|öneri|konu|topic)\s*:\s*/iu', '', $t);
        $t = preg_replace('/^[-–—]\s*/u', '', $t);

        return trim($t, "\"'\n\r#* ");
    }
}
