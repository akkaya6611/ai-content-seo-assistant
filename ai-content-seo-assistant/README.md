# AI Content & SEO Assistant (WordPress Plugin)

**AI Content & SEO Assistant**, WordPress için geliştirilmiş modern, hızlı ve çoklu yapay zeka sağlayıcılı (OpenAI, Anthropic Claude, Google Gemini, DeepSeek, OpenRouter, Z.ai, MiniMax) bir içerik üretim ve SEO optimizasyon eklentisidir.

---

## 🌟 Öne Çıkan Özellikler

### 1. 🤖 Çoklu AI Sağlayıcısı (BYOK - Kendi Key'ini Getir)
- **OpenRouter**: 32 ücretsiz model (`qwen/qwen3-coder:free`, `google/gemma-4-31b-it:free` vb.) ile tek key üzerinden sınırsız esneklik.
- **Google Gemini**: `gemini-2.0-flash` ve `gemini-2.0-pro` ile ultra hızlı ve akıllı içerik üretimi.
- **DeepSeek**: `deepseek-chat` (V3) ve `deepseek-reasoner` (R1) modelleri ile piyasadan 10 kat daha uygun maliyetle derin muhakeme ve yazım.
- **Anthropic Claude**: `claude-3-7-sonnet` ve `claude-3-5-haiku` ile en doğal ve kaliteli Türkçe/İngilizce anlatım.
- **OpenAI**: `gpt-4o` ve `gpt-4o-mini` modelleri.
- **Özel / Anthropic Uyumlu Endpoint**: Z.ai, MiniMax veya yerel Ollama/LM Studio uç noktaları.

### 2. ✍️ Gutenberg & Klasik Editör İçerik Asistanı
- **Makale Üretici**: Konu ve anahtar kelimelere göre başlıklar (H2, H3), listeler ve paragraflar içeren tam makaleler oluşturma.
- **Taslak (Outline) Oluşturucu**: Mantıksal akışa sahip içerik planı çıkarma.
- **Giriş / Sonuç / SSS (FAQ)**: Kancalı girişler ve Google SERP için Sıkça Sorulan Sorular bölümü oluşturma.
- **Tek Tıkla Editöre Aktar**: Üretilen içeriği blok blok Gutenberg editörüne veya TinyMCE editörüne otomatik ekleme.

### 3. 🔄 Akıllı Metin İyileştirici
- **Yeniden Yaz (Paraphrase)**: Anlamı koruyarak akıcı ve özgün kelimelerle yeniden yazma.
- **Genişlet (Expand)**: Kısa kalmış paragrafları detay ve örneklerle zenginleştirme.
- **Özetle (Summarize)**: Uzun metinlerden hap bilgiler ve özet çıkarma.
- **Dilbilgisi & İmla**: Yazım ve noktalama hatalarını anında düzeltme.

### 4. 🔍 Canlı Google SERP Önizlemesi & SEO Motoru
- **Google Arama Sonucu Simülatörü**: Masaüstü ve Mobil arama sonuçlarında sayfanızın nasıl görüneceğini gerçek zamanlı canlı izleyin.
- **AI ile Meta Başlık & Açıklama Üretimi**: CTR (Tıklama Oranı) odaklı SEO meta başlığı ve açıklaması üretme.
- **Karakter Sayaçları ve SEO Kontrol Listesi**: 40-60 karakter başlık ve 120-160 karakter açıklama standartlarına göre anlık renkli durum bildirimleri.
- **Otomatik Schema.org JSON-LD**: `Article` ve `BlogPosting` yapısal verilerini otomatik olarak `<head>` içine basar.
- **OpenGraph & Twitter Cards**: Facebook, WhatsApp ve Twitter paylaşımları için otomatik sosyal medya meta etiketleri.

---

## 📦 Kurulum

1. `ai-content-seo-assistant` klasörünü zip haline getirin (veya doğrudan `wp-content/plugins/` klasörüne kopyalayın).
2. WordPress Yönetim Panelinde **Eklentiler > Yeni Ekle > Eklenti Yükle** adımlarını izleyerek zip dosyasını yükleyin ve etkinleştirin.
3. **Ayarlar > AI SEO Asistanı** menüsüne gidin.
4. Kullanmak istediğiniz sağlayıcının (örn. OpenRouter veya Gemini) API anahtarını girin ve **"Bağlantıyı Test Et"** butonuna basarak doğruluğunu test edin.
5. Ayarları kaydedin. Artık herhangi bir Yazı veya Sayfa düzenlerken ekranın altındaki **⚡ AI İçerik & SEO Asistanı** panelini kullanabilirsiniz!

---

## 🛡️ Güvenlik & Standartlar
- Tüm AJAX istekleri WordPress Nonce (`check_ajax_referer`) ile korunur.
- Yalnızca `edit_posts` ve `manage_options` yetkisine sahip kullanıcılar işlem yapabilir.
- Tüm girdiler `sanitize_*`, tüm çıktılar `esc_*` ve `wp_kses_*` fonksiyonlarından geçirilir.
- Yoast SEO, Rank Math veya All in One SEO yüklüyse etiket çakışmalarını önleyen otomatik tespit mekanizmasına sahiptir.

---

## 👨‍💻 Geliştirici & Lisans
- **Yazılımcı:** Serkan AKKAYA
- **Web Sitesi:** [misteknoloji360.com.tr](https://misteknoloji360.com.tr/)
- **Lisans:** GPL v2 veya sonrası lisansı ile lisanslanmıştır.
