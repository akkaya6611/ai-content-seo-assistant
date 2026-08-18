/**
 * AI Content & SEO Assistant Admin Settings JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Tab Switching
        $('.ai-seo-nav-tabs .nav-tab').on('click', function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            $('.ai-seo-nav-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.ai-seo-admin-tab-pane').hide();
            $('#tab-pane-' + tabId).fadeIn(150);
        });

        // Test API Connection
        $('.ai-test-api-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var provider = $btn.data('provider');
            var $card = $btn.closest('.ai-seo-card');
            var $status = $card.find('.ai-test-status');

            // Değerleri form alanlarından oku
            var key = $card.find('#' + provider + '_key').val() || $card.find('input[type="password"]').val() || '';
            var model = $card.find('#' + provider + '_model').val() || $card.find('select, input').filter(function() {
                return this.id && this.id.indexOf(provider + '_model') !== -1;
            }).val() || '';
            var baseUrl = $card.find('#custom_base_url').val() || '';

            $status.removeClass('success error').text(aiSeoSettings.strings.testing);
            $btn.prop('disabled', true);

            $.ajax({
                url: aiSeoSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_test_connection',
                    security: aiSeoSettings.nonce,
                    provider: provider,
                    key: key,
                    model: model,
                    base_url: baseUrl
                },
                success: function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                    } else {
                        $status.addClass('error').text(response.data.message || aiSeoSettings.strings.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $status.addClass('error').text(aiSeoSettings.strings.error);
                }
            });
        });

        // Manual Trigger Autopilot Generation (Test)
        $('#ai-btn-trigger-autopilot-now').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $status = $('#ai-autopilot-test-status');

            if (!confirm('Otomatik pilot kuyruğundaki sıradaki konu için hemen 1 makale üretilip WordPress\'e kaydedilsin mi?')) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.ai-spin-icon').show();
            $status.removeClass('success error').text('Yapay zeka makaleyi hazırlıyor ve yayınlıyor (yaklaşık 10-15 sn sürebilir)...');

            $.ajax({
                url: aiSeoSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_trigger_autopilot_now',
                    security: aiSeoSettings.nonce
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    if (res.success) {
                        $status.addClass('success').html(res.data.message + ' <a href="' + res.data.url + '" target="_blank" style="margin-left:8px; font-weight:600; color:#1e7e34; text-decoration:underline;">Yazıyı İncele &rarr;</a>');
                    } else {
                        $status.addClass('error').text(res.data.message || aiSeoSettings.strings.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    $status.addClass('error').text(aiSeoSettings.strings.error);
                }
            });
        });

        // Check for Updates Now
        $('#ai-btn-check-updates-now').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $status = $('#ai-update-check-status');

            $btn.prop('disabled', true);
            $btn.find('.ai-spin-icon').show();
            $status.removeClass('success error').css('color', '#646970').text('Güncelleme sunucusu kontrol ediliyor...');

            $.ajax({
                url: aiSeoSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_force_check_updates',
                    security: aiSeoSettings.nonce
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    if (res.success) {
                        if (res.data.has_update) {
                            $status.css('color', '#1e7e34').html(res.data.message + ' <a href="' + res.data.plugins_url + '" class="button button-primary" style="margin-left:10px;">Eklentiler Sayfasına Git &rarr;</a>');
                        } else {
                            $status.css('color', '#1e7e34').text(res.data.message);
                        }
                    } else {
                        $status.css('color', '#d63638').text(res.data.message || aiSeoSettings.strings.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    $status.css('color', '#d63638').text(aiSeoSettings.strings.error);
                }
            });
        });

    });

})(jQuery);
