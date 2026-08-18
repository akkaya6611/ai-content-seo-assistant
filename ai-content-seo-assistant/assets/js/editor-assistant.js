/**
 * AI Content & SEO Assistant Editor Assistant JS
 * Gutenberg & Classic Editor Entegrasyonu
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // 1. Sekmeler Arası Geçiş
        $('.ai-seo-tab-btn').on('click', function(e) {
            e.preventDefault();
            var tabId = $(this).data('tab');

            $('.ai-seo-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.ai-seo-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
        });

        // 2. SERP Masaüstü / Mobil Görünüm Değişimi
        $('.ai-serp-toggle-btn').on('click', function(e) {
            e.preventDefault();
            var mode = $(this).data('mode');

            $('.ai-serp-toggle-btn').removeClass('active');
            $(this).addClass('active');

            var $box = $('#ai-serp-box');
            if (mode === 'mobile') {
                $box.removeClass('desktop-mode').addClass('mobile-mode');
            } else {
                $box.removeClass('mobile-mode').addClass('desktop-mode');
            }
        });

        // 3. Canlı SEO ve SERP Güncellemesi
        function updateSerpAndChecklist() {
            var title = $('#ai_seo_meta_title').val() || getPostTitle() || 'Örnek Sayfa Başlığı';
            var desc = $('#ai_seo_meta_desc').val() || 'Sayfanızın arama motoru sonuçlarında görüntülenecek meta açıklaması burada canlı olarak simüle edilir.';
            var keyword = ($('#ai_seo_focus_keyword').val() || '').toLowerCase().trim();

            // SERP Önizlemesini Güncelle
            $('#ai-serp-preview-title').text(title);
            $('#ai-serp-preview-desc').text(desc);

            // Karakter Sayaçları
            var titleLen = ($('#ai_seo_meta_title').val() || '').length;
            var descLen = ($('#ai_seo_meta_desc').val() || '').length;

            $('#ai-title-count').text(titleLen);
            $('#ai-desc-count').text(descLen);

            // Başlık Rozeti
            var $titleBadge = $('#ai-title-badge');
            if (titleLen === 0) {
                $titleBadge.text('').removeClass('optimal warning danger');
            } else if (titleLen >= 40 && titleLen <= 60) {
                $titleBadge.text('Mükemmel').removeClass('warning danger').addClass('optimal');
            } else if (titleLen < 40) {
                $titleBadge.text('Çok Kısa').removeClass('optimal danger').addClass('warning');
            } else {
                $titleBadge.text('Çok Uzun').removeClass('optimal warning').addClass('danger');
            }

            // Açıklama Rozeti
            var $descBadge = $('#ai-desc-badge');
            if (descLen === 0) {
                $descBadge.text('').removeClass('optimal warning danger');
            } else if (descLen >= 120 && descLen <= 160) {
                $descBadge.text('Mükemmel').removeClass('warning danger').addClass('optimal');
            } else if (descLen < 120) {
                $descBadge.text('Kısa').removeClass('optimal danger').addClass('warning');
            } else {
                $descBadge.text('Çok Uzun').removeClass('optimal warning').addClass('danger');
            }

            // Checklist Kontrolleri
            updateChecklist('#check-title-len', titleLen >= 40 && titleLen <= 60);
            updateChecklist('#check-desc-len', descLen >= 120 && descLen <= 160);

            if (keyword.length > 0) {
                updateChecklist('#check-keyword-title', title.toLowerCase().indexOf(keyword) !== -1);
                updateChecklist('#check-keyword-desc', desc.toLowerCase().indexOf(keyword) !== -1);
            } else {
                updateChecklist('#check-keyword-title', false);
                updateChecklist('#check-keyword-desc', false);
            }
        }

        function updateChecklist(selector, isPassed) {
            var $el = $(selector);
            var $icon = $el.find('.dashicons');
            if (isPassed) {
                $el.removeClass('failed').addClass('passed');
                $icon.removeClass('dashicons-marker dashicons-no').addClass('dashicons-yes-alt');
            } else {
                $el.removeClass('passed').addClass('failed');
                $icon.removeClass('dashicons-marker dashicons-yes-alt').addClass('dashicons-marker');
            }
        }

        function getPostTitle() {
            // Gutenberg başlığı
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                var gTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                if (gTitle) return gTitle;
            }
            // Classic editor başlığı
            return $('#title').val() || '';
        }

        function getPostContent() {
            // Gutenberg içeriği
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                var gContent = wp.data.select('core/editor').getEditedPostAttribute('content');
                if (gContent) return gContent;
            }
            // Classic Editor (TinyMCE)
            if (window.tinyMCE && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                return tinyMCE.get('content').getContent();
            }
            // Textarea
            return $('#content').val() || '';
        }

        $('#ai_seo_meta_title, #ai_seo_meta_desc, #ai_seo_focus_keyword').on('input keyup change', function() {
            updateSerpAndChecklist();
        });

        // Sayfa yüklendiğinde ilk SERP hesaplamasını yap
        setTimeout(updateSerpAndChecklist, 500);

        // 4. AI ile Tek Tıkla Meta Başlık ve Açıklama Üretme
        $('#ai-btn-gen-title').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).find('.dashicons').addClass('ai-spin-icon');

            $.ajax({
                url: aiSeoEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_generate_meta',
                    security: aiSeoEditor.nonce,
                    provider: $('#ai_gen_provider').val() || '',
                    field: 'title',
                    title: getPostTitle() || $('#ai_gen_topic').val(),
                    content: getPostContent(),
                    keyword: $('#ai_seo_focus_keyword').val()
                },
                success: function(res) {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('ai-spin-icon');
                    if (res.success && res.data.result) {
                        $('#ai_seo_meta_title').val(res.data.result).trigger('input');
                    } else {
                        alert(res.data.message || aiSeoEditor.strings.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('ai-spin-icon');
                    alert(aiSeoEditor.strings.error);
                }
            });
        });

        $('#ai-btn-gen-desc').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).find('.dashicons').addClass('ai-spin-icon');

            $.ajax({
                url: aiSeoEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_generate_meta',
                    security: aiSeoEditor.nonce,
                    provider: $('#ai_gen_provider').val() || '',
                    field: 'desc',
                    title: getPostTitle() || $('#ai_gen_topic').val(),
                    content: getPostContent(),
                    keyword: $('#ai_seo_focus_keyword').val()
                },
                success: function(res) {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('ai-spin-icon');
                    if (res.success && res.data.result) {
                        $('#ai_seo_meta_desc').val(res.data.result).trigger('input');
                    } else {
                        alert(res.data.message || aiSeoEditor.strings.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('ai-spin-icon');
                    alert(aiSeoEditor.strings.error);
                }
            });
        });

        // 5. İçerik Üretici (AI Content Generator)
        $('#ai-btn-generate-content').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $output = $('#ai-content-output-box');
            var topic = $('#ai_gen_topic').val() || getPostTitle();
            var keywords = $('#ai_gen_keywords').val();
            var genType = $('#ai_gen_type').val();
            var tone = $('#ai_gen_tone').val();
            var provider = $('#ai_gen_provider').val() || '';

            if (!topic) {
                alert('Lütfen bir konu veya başlık girin.');
                $('#ai_gen_topic').focus();
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.ai-spin-icon').show();
            $output.html('<div style="color:#646970; text-align:center; padding:40px 0;"><span class="dashicons dashicons-update ai-spin-icon" style="font-size:28px; width:28px; height:28px;"></span><br><br>' + aiSeoEditor.strings.generating + '</div>');

            $.ajax({
                url: aiSeoEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_generate_content',
                    security: aiSeoEditor.nonce,
                    provider: provider,
                    topic: topic,
                    keywords: keywords,
                    gen_type: genType,
                    tone: tone
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    if (res.success && res.data.content) {
                        $output.html(res.data.content);
                    } else {
                        var $errDiv = $('<div>').css({color: '#d63638', padding: '20px'}).text(res.data.message || aiSeoEditor.strings.error);
                        $output.empty().append($errDiv);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.ai-spin-icon').hide();
                    var $errDiv = $('<div>').css({color: '#d63638', padding: '20px'}).text(aiSeoEditor.strings.error);
                    $output.empty().append($errDiv);
                }
            });
        });

        // 6. Metin İyileştirici (Rephrase, Expand, Summarize, Grammar)
        $('.ai-rephrase-action-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var action = $btn.data('action');
            var text = $('#ai_rephrase_input').val();
            var $output = $('#ai-rephrase-output-box');
            var provider = $('#ai_gen_provider').val() || '';

            if (!text) {
                alert('Lütfen iyileştirilecek metni girin veya editörden seçin.');
                return;
            }

            $btn.prop('disabled', true);
            $output.html('<div style="color:#646970; text-align:center; padding:30px 0;"><span class="dashicons dashicons-update ai-spin-icon" style="font-size:24px; width:24px; height:24px;"></span><br><br>' + aiSeoEditor.strings.generating + '</div>');

            $.ajax({
                url: aiSeoEditor.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_seo_rephrase_text',
                    security: aiSeoEditor.nonce,
                    provider: provider,
                    text: text,
                    rephrase_action: action
                },
                success: function(res) {
                    $btn.prop('disabled', false);
                    if (res.success && res.data.content) {
                        $output.html(res.data.content);
                    } else {
                        var $errDiv = $('<div>').css({color: '#d63638', padding: '15px'}).text(res.data.message || aiSeoEditor.strings.error);
                        $output.empty().append($errDiv);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    var $errDiv = $('<div>').css({color: '#d63638', padding: '15px'}).text(aiSeoEditor.strings.error);
                    $output.empty().append($errDiv);
                }
            });
        });

        // 7. Panoya Kopyalama
        $('#ai-btn-copy-output').on('click', function() {
            var html = $('#ai-content-output-box').html();
            if (!html) return;

            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val($('#ai-content-output-box').text() || html).select();
            document.execCommand('copy');
            $temp.remove();

            var $btn = $(this);
            var originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> ' + aiSeoEditor.strings.copied);
            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        });

        function cleanTitleStr(title) {
            if (!title) return '';
            var t = $.trim(title);
            t = t.replace(/^["'“”]/, '').replace(/["'“”]$/, '');
            if (/(?:here[’']?s\s+a\s+|s\s+a\s+|\*?\*?)thinking\s+process|analyze\s+user\s+input|constraint\s+\d/i.test(t)) {
                var m = t.match(/[‘'"]([^‘'"]{10,})[’'"]/);
                if (m) {
                    t = m[1];
                } else {
                    var mTopic = t.match(/topic:?\s*[‘'"*]*([^‘'"*\r\n]{8,})/i);
                    if (mTopic) {
                        t = mTopic[1];
                    }
                }
            }
            t = t.replace(/\([A-Za-z\s,:'’\-.\?]{8,}\)/g, '');
            t = t.replace(/^(?:başlık|seo başlığı|title|öneri|konu|topic)\s*:\s*/i, '');
            t = t.replace(/^(\d+[\.\-\)]\s*)/, '');
            t = t.replace(/^[-–—]\s*/, '');
            return $.trim(t);
        }

        // 8. Başlığı WordPress Editörüne Aktarma
        function setPostTitle(title) {
            if (!title) return;
            title = cleanTitleStr(title);
            if (!title) return;

            // Gutenberg Block Editor
            if (window.wp && wp.data && wp.data.dispatch('core/editor')) {
                try {
                    wp.data.dispatch('core/editor').editPost({ title: title });
                } catch (e) {}
            }
            // Classic Editor
            $('#title').val(title);
            $('#title-prompt-text').addClass('screen-reader-text');

            // Form alanlarını güncelle
            $('#ai_gen_topic').val(title);
            if (!$('#ai_seo_meta_title').val() || $('#ai_seo_meta_title').val().length < 6) {
                $('#ai_seo_meta_title').val(title);
            }
            updateSerpAndChecklist();
        }

        // Önerilen Başlığı Seç ve Uygula
        $(document).on('click', '.ai-btn-apply-title', function(e) {
            e.preventDefault();
            var title = $(this).data('title');
            setPostTitle(title);
            var $btn = $(this);
            var orig = $btn.text();
            $btn.text('✓ Seçildi!').css('background', '#16a34a');
            setTimeout(function() {
                $btn.text(orig).css('background', '#2563eb');
            }, 2500);
        });

        // 9. Editöre Aktar (Insert to Gutenberg or Classic Editor)
        function insertContentToEditor(contentHtml) {
            if (!contentHtml) return;

            // Eğer mevcut yazı başlığı boş veya çok kısaysa (örn: "Ahş"), içerikteki ilk H1/H2 başlığını otomatik başlık yap
            var currentTitle = getPostTitle();
            if (!currentTitle || $.trim(currentTitle).length < 6) {
                var match = contentHtml.match(/<h[12][^>]*>(.*?)<\/h[12]>/i);
                if (match && match[1]) {
                    var cleanH = $('<div>').html(match[1]).text().trim();
                    if (cleanH.length >= 6) {
                        setPostTitle(cleanH);
                    }
                } else if ($('#ai_gen_topic').val() && $.trim($('#ai_gen_topic').val()).length >= 6) {
                    setPostTitle($('#ai_gen_topic').val().trim());
                }
            }

            // Gutenberg Block Editor
            if (window.wp && wp.data && wp.blocks && wp.data.dispatch('core/block-editor')) {
                try {
                    var blocks = wp.blocks.rawHandler({ HTML: contentHtml });
                    if (blocks && blocks.length > 0) {
                        wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                        showNotice(aiSeoEditor.strings.inserted);
                        return;
                    }
                } catch (err) {
                    console.error('Gutenberg block insertion error:', err);
                }
            }

            // Classic Editor TinyMCE
            if (window.tinyMCE && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                tinyMCE.get('content').execCommand('mceInsertContent', false, contentHtml);
                showNotice(aiSeoEditor.strings.inserted);
                return;
            }

            // Plain Textarea Fallback
            var $textarea = $('#content');
            if ($textarea.length) {
                var currentVal = $textarea.val();
                $textarea.val(currentVal + (currentVal ? "\n\n" : "") + contentHtml);
                showNotice(aiSeoEditor.strings.inserted);
            }
        }

        $('#ai-btn-insert-editor').on('click', function() {
            insertContentToEditor($('#ai-content-output-box').html());
        });

        $('#ai-btn-insert-rephrase').on('click', function() {
            insertContentToEditor($('#ai-rephrase-output-box').html());
        });

        function showNotice(msg) {
            alert(msg);
        }

    });

})(jQuery);
