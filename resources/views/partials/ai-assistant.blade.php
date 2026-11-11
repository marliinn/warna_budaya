<div class="ai-assistant" data-ai-assistant data-endpoint="{{ route('ai.chat') }}">
    <button class="ai-assistant-launcher" type="button" data-ai-open aria-label="Buka AI Sahabat Budaya" aria-controls="ai-chat-panel">
        <span class="ai-assistant-launcher-mark" aria-hidden="true">✺</span>
        <span class="ai-assistant-launcher-label">Teman Budaya</span>
    </button>

    <section class="ai-chat-panel" id="ai-chat-panel" data-ai-panel aria-label="Percakapan AI Sahabat Budaya" aria-hidden="true" hidden>
        <header class="ai-chat-header">
            <div class="ai-chat-brand">
                <span class="ai-chat-brand-mark" aria-hidden="true">✺</span>
                <span>
                    <strong>AI Sahabat Budaya</strong>
                    <small><span class="ai-chat-online-dot"></span> Teman jelajah Nusantara</small>
                </span>
            </div>
            <div class="ai-chat-controls">
                <button type="button" data-ai-history aria-label="Lihat riwayat percakapan" title="Riwayat">◷</button>
                <button type="button" data-ai-minimize aria-label="Minimalkan percakapan" title="Minimalkan">−</button>
                <button type="button" data-ai-close aria-label="Tutup percakapan" title="Tutup">×</button>
            </div>
        </header>

        <div class="ai-chat-body" data-ai-body>
            <div class="ai-chat-view" data-ai-chat-view>
                <div class="ai-chat-messages" data-ai-messages aria-live="polite" aria-relevant="additions text">
                    <div class="ai-chat-welcome" data-ai-welcome>
                        <span class="ai-chat-welcome-mark" aria-hidden="true">✺</span>
                        <p class="ai-chat-eyebrow">SALAM NUSANTARA</p>
                        <h2>Halo, Sahabat Budaya!</h2>
                        <p>Ada cerita Indonesia yang ingin kamu jelajahi hari ini?</p>
                        <div class="ai-chat-suggestions">
                            <button type="button" data-ai-suggestion="Apa makna motif batik Mega Mendung?">Makna motif batik</button>
                            <button type="button" data-ai-suggestion="Ceritakan tentang Kerajaan Majapahit dengan singkat.">Kerajaan Nusantara</button>
                            <button type="button" data-ai-suggestion="Apa keunikan rumah adat Tongkonan di Toraja?">Rumah adat Indonesia</button>
                        </div>
                    </div>
                </div>
                <p class="ai-chat-error" data-ai-error role="alert" hidden></p>
                <form class="ai-chat-form" data-ai-form>
                    <label class="sr-only" for="ai-chat-input">Tulis pertanyaan tentang budaya Indonesia</label>
                    <textarea id="ai-chat-input" data-ai-input rows="1" maxlength="2000" placeholder="Tanya tentang budaya Indonesia..." required></textarea>
                    <button type="submit" data-ai-send aria-label="Kirim pesan">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.2 18-3.9-7.9L2 9.2 21 3Z"></path><path d="M10 13 21 3"></path></svg>
                    </button>
                </form>
                <p class="ai-chat-disclaimer">Jawaban AI dapat keliru. Periksa kembali fakta penting melalui sumber tepercaya.</p>
            </div>

            <div class="ai-chat-history-view" data-ai-history-view hidden>
                <div class="ai-chat-history-heading">
                    <div>
                        <p class="ai-chat-eyebrow">PERJALANANMU</p>
                        <h2>Riwayat obrolan</h2>
                    </div>
                    <button type="button" data-ai-new-chat>＋ Obrolan baru</button>
                </div>
                <div class="ai-chat-history-list" data-ai-history-list></div>
                <button class="ai-chat-back" type="button" data-ai-back>← Kembali ke obrolan</button>
            </div>
        </div>
    </section>
</div>
