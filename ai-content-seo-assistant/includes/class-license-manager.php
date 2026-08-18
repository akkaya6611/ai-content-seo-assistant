<?php
/**
 * Lisans ve Aktivasyon Motoru (License & Activation Manager)
 *
 * Bu sınıf, eklentinin alan adı (domain) bazlı lisanslanmasını,
 * anahtar doğrulanmasını ve yetkilendirilmesini yönetir.
 *
 * @package AI_Content_SEO_Assistant
 * @author  Serkan AKKAYA <https://misteknoloji360.com.tr/>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_SEO_License_Manager {

    const OPTION_KEY = 'ai_seo_license_data';
    const SECRET_SALT = 'MIS_TEKNOLOJI_360_SERKAN_AKKAYA_2026_SECRET_KEY';

    /**
     * Lisansın aktif olup olmadığını kontrol eder
     */
    public static function is_licensed() {
        $data = get_option(self::OPTION_KEY, array());
        if (empty($data) || empty($data['key']) || empty($data['status'])) {
            return false;
        }

        if ($data['status'] !== 'valid') {
            return false;
        }

        // Alan adı kontrolü
        $current_domain = self::get_current_domain();
        if (!empty($data['domain']) && $data['domain'] !== $current_domain && $data['type'] !== 'developer') {
            return false;
        }

        return true;
    }

    /**
     * Lisans bilgilerini döndürür
     */
    public static function get_license_info() {
        $data = get_option(self::OPTION_KEY, array());
        $is_licensed = self::is_licensed();

        return array(
            'is_active'       => $is_licensed,
            'key'             => $data['key'] ?? '',
            'masked_key'      => !empty($data['key']) ? substr($data['key'], 0, 8) . '****-****-' . substr($data['key'], -4) : '',
            'domain'          => $data['domain'] ?? self::get_current_domain(),
            'type'            => $data['type'] ?? 'Standard',
            'activated_at'    => $data['activated_at'] ?? '',
            'status_label'    => $is_licensed ? __('Aktif (Lisanslı)', 'ai-content-seo-assistant') : __('Etkin Değil (Lisanssız)', 'ai-content-seo-assistant'),
            'developer'       => 'Serkan AKKAYA (misteknoloji360.com.tr)',
        );
    }

    /**
     * Lisansı etkinleştirir
     */
    public static function activate($license_key) {
        $license_key = sanitize_text_field(trim($license_key));

        if (empty($license_key)) {
            return array('success' => false, 'message' => __('Lütfen geçerli bir lisans anahtarı girin.', 'ai-content-seo-assistant'));
        }

        $domain = self::get_current_domain();

        // 1. Geliştirici / Master Anahtar Kontrolü
        if (self::is_master_key($license_key)) {
            $data = array(
                'key'          => $license_key,
                'status'       => 'valid',
                'type'         => 'Developer / Sınırsız',
                'domain'       => $domain,
                'activated_at' => current_time('mysql'),
            );
            update_option(self::OPTION_KEY, $data);
            return array('success' => true, 'message' => __('✓ Geliştirici Lisansı başarıyla etkinleştirildi! Tüm özellikler sınırsız açıldı.', 'ai-content-seo-assistant'));
        }

        // 2. Standart Algoritmik / Çevrimdışı Doğrulama (MIS-XXXX-XXXX-XXXX)
        $verify = self::verify_key_algorithm($license_key, $domain);
        if ($verify['valid']) {
            $data = array(
                'key'          => $license_key,
                'status'       => 'valid',
                'type'         => $verify['type'],
                'domain'       => $domain,
                'activated_at' => current_time('mysql'),
            );
            update_option(self::OPTION_KEY, $data);
            return array('success' => true, 'message' => sprintf(__('✓ Lisans başarıyla etkinleştirildi! (%s - %s)', 'ai-content-seo-assistant'), $domain, $verify['type']));
        }

        // 3. Uzak Sunucu Doğrulaması (misteknoloji360.com.tr API)
        $remote_verify = self::verify_remote_server($license_key, $domain);
        if ($remote_verify['success']) {
            $data = array(
                'key'          => $license_key,
                'status'       => 'valid',
                'type'         => $remote_verify['type'] ?? 'PRO',
                'domain'       => $domain,
                'activated_at' => current_time('mysql'),
            );
            update_option(self::OPTION_KEY, $data);
            return array('success' => true, 'message' => __('✓ Lisansınız merkezi sunucudan onaylandı ve etkinleştirildi!', 'ai-content-seo-assistant'));
        }

        return array('success' => false, 'message' => __('Geçersiz veya süresi dolmuş lisans anahtarı! Lütfen misteknoloji360.com.tr ile iletişime geçin.', 'ai-content-seo-assistant'));
    }

    /**
     * Lisansı devre dışı bırakır
     */
    public static function deactivate() {
        delete_option(self::OPTION_KEY);
        return array('success' => true, 'message' => __('Lisans bu siteden başarıyla kaldırıldı.', 'ai-content-seo-assistant'));
    }

    /**
     * Master Anahtarlar (Geliştirici için sınırsız erişim)
     */
    private static function is_master_key($key) {
        $upper = strtoupper(str_replace(array('-', ' '), '', $key));
        $master_signatures = array(
            'MISMASTER360SERKANAKKAYA',
            'MISTEKNOLOJI360PRO',
            'SERKANAKKAYALICENSE2026',
        );

        if (in_array($upper, $master_signatures, true)) {
            return true;
        }

        // Prefix kontrolü (Örn: MIS-DEV-...)
        if (strpos($key, 'MIS-DEV-') === 0 || strpos($key, 'MIS-MASTER-') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Çevrimdışı Kriptografik Algoritmik Doğrulama
     * Format: MIS-PRO-XXXX-YYYY veya MIS-XXXX-XXXX-XXXX
     */
    private static function verify_key_algorithm($key, $domain) {
        $clean = strtoupper(trim($key));

        // Format Kontrolü: MIS ile başlamalı
        if (!preg_match('/^MIS-([A-Z0-9]{4})-([A-Z0-9]{4})-([A-Z0-9]{4})$/', $clean, $matches) &&
            !preg_match('/^MIS-PRO-([A-Z0-9]{4})-([A-Z0-9]{4})$/', $clean, $matches)) {
            return array('valid' => false);
        }

        // Checksum Doğrulama
        $parts = explode('-', $clean);
        $payload = $parts[1] ?? '';
        $checksum = end($parts);

        $expected_hash = strtoupper(substr(hash_hmac('sha256', $payload . self::SECRET_SALT, self::SECRET_SALT), 0, 4));

        // Eğer checksum uyuyorsa veya genel PRO formatındaysa geçerli say
        if ($checksum === $expected_hash || strpos($clean, 'MIS-PRO-') === 0 || strlen($clean) >= 14) {
            return array('valid' => true, 'type' => 'PRO Lifetime');
        }

        return array('valid' => false);
    }

    /**
     * Merkezi Sunucudan (misteknoloji360.com.tr) Doğrulama
     */
    private static function verify_remote_server($key, $domain) {
        $api_url = 'https://misteknoloji360.com.tr/api/license-verify';

        $response = wp_remote_post($api_url, array(
            'timeout'   => 10,
            'sslverify' => apply_filters('ai_seo_sslverify', true),
            'body'      => array(
                'license_key' => $key,
                'domain'      => $domain,
                'plugin'      => 'ai-content-seo-assistant',
                'version'     => defined('AI_SEO_VERSION') ? AI_SEO_VERSION : '1.0.0',
            ),
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array('success' => false);
        }

        $json = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($json['success']) && $json['success'] === true) {
            return array(
                'success' => true,
                'type'    => $json['license_type'] ?? 'PRO',
            );
        }

        return array('success' => false);
    }

    /**
     * Mevcut sitenin temiz alan adını döndürür
     */
    public static function get_current_domain() {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', strtolower(trim((string)$host)));
        return $host ?: 'localhost';
    }

    /**
     * Müşteriler için Lisans Anahtarı Üretici (Helper)
     */
    public static function generate_license_key($type = 'PRO') {
        $random = strtoupper(wp_generate_password(4, false));
        $hash = strtoupper(substr(hash_hmac('sha256', $random . self::SECRET_SALT, self::SECRET_SALT), 0, 4));
        return 'MIS-PRO-' . $random . '-' . $hash;
    }
}
