const assistant = document.querySelector('[data-ai-assistant]');

if (assistant) {
    const panel = assistant.querySelector('[data-ai-panel]');
    const openButton = assistant.querySelector('[data-ai-open]');
    const closeButton = assistant.querySelector('[data-ai-close]');
    const minimizeButton = assistant.querySelector('[data-ai-minimize]');
    const historyButton = assistant.querySelector('[data-ai-history]');
    const chatView = assistant.querySelector('[data-ai-chat-view]');
    const historyView = assistant.querySelector('[data-ai-history-view]');
    const historyList = assistant.querySelector('[data-ai-history-list]');
    const messagesElement = assistant.querySelector('[data-ai-messages]');
    const welcome = assistant.querySelector('[data-ai-welcome]');
    const form = assistant.querySelector('[data-ai-form]');
    const input = assistant.querySelector('[data-ai-input]');
    const sendButton = assistant.querySelector('[data-ai-send]');
    const errorElement = assistant.querySelector('[data-ai-error]');
    const storageKey = 'warna-budaya-ai-chat-v1';
    const conversationLimit = 12;

    const readConversations = () => {
        try {
            const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(value) ? value.filter((item) => item && Array.isArray(item.messages)) : [];
        } catch {
            return [];
        }
    };

    let conversations = readConversations();
    let activeConversation = conversations[0] || null;

    const saveConversations = () => {
        conversations = conversations
            .filter((conversation) => conversation.messages.length)
            .sort((a, b) => b.updatedAt - a.updatedAt)
            .slice(0, 20);
        localStorage.setItem(storageKey, JSON.stringify(conversations));
    };

    const showError = (message) => {
        errorElement.textContent = message;
        errorElement.hidden = false;
    };

    const clearError = () => {
        errorElement.textContent = '';
        errorElement.hidden = true;
    };

    const appendFormattedText = (parent, text) => {
        const formatting = /(\*\*[^*]+\*\*|__[^_]+__|`[^`]+`|\*[^*]+\*|_[^_]+_)/g;
        let lastIndex = 0;
        let match;

        while ((match = formatting.exec(text)) !== null) {
            if (match.index > lastIndex) {
                parent.append(document.createTextNode(text.slice(lastIndex, match.index)));
            }

            const token = match[0];
            const element = token.startsWith('**') || token.startsWith('__')
                ? document.createElement('strong')
                : token.startsWith('`')
                    ? document.createElement('code')
                    : document.createElement('em');
            element.textContent = token.slice(token.startsWith('**') || token.startsWith('__') ? 2 : 1, - (token.startsWith('**') || token.startsWith('__') ? 2 : 1));
            parent.append(element);
            lastIndex = formatting.lastIndex;
        }

        if (lastIndex < text.length) {
            parent.append(document.createTextNode(text.slice(lastIndex)));
        }
    };

    const renderAssistantAnswer = (bubble, content) => {
        const lines = content.replace(/\r\n?/g, '\n').split('\n');
        let list = null;
        let listType = null;

        lines.forEach((line) => {
            const trimmedLine = line.trim().replace(/^>\s?/, '');
            if (!trimmedLine) {
                list = null;
                listType = null;
                return;
            }

            const listMatch = trimmedLine.match(/^([-*•]|\d+[.)])\s+(.+)$/);
            const headingMatch = trimmedLine.match(/^#{1,3}\s+(.+)$/);

            if (listMatch) {
                const nextListType = /^\d/.test(listMatch[1]) ? 'ol' : 'ul';
                if (!list || listType !== nextListType) {
                    list = document.createElement(nextListType);
                    bubble.append(list);
                    listType = nextListType;
                }

                const item = document.createElement('li');
                appendFormattedText(item, listMatch[2]);
                list.append(item);
                return;
            }

            list = null;
            listType = null;
            const paragraph = document.createElement(headingMatch ? 'strong' : 'p');
            appendFormattedText(paragraph, headingMatch ? headingMatch[1] : trimmedLine);
            bubble.append(paragraph);
        });
    };

    const appendMessage = (role, content) => {
        const row = document.createElement('div');
        row.className = `ai-chat-message${role === 'user' ? ' is-user' : ''}`;
        const bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble';
        if (role === 'assistant') {
            renderAssistantAnswer(bubble, content);
        } else {
            bubble.textContent = content;
        }
        row.append(bubble);
        messagesElement.append(row);
        messagesElement.scrollTop = messagesElement.scrollHeight;
        return row;
    };

    const renderConversation = () => {
        messagesElement.replaceChildren();
        const hasMessages = Boolean(activeConversation?.messages.length);
        if (!hasMessages) {
            messagesElement.append(welcome);
            welcome.hidden = false;
            return;
        }

        welcome.hidden = true;
        activeConversation.messages.forEach((message) => appendMessage(message.role, message.content));
    };

    const createConversation = () => {
        activeConversation = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            title: 'Percakapan baru',
            updatedAt: Date.now(),
            messages: [],
        };
        conversations.unshift(activeConversation);
        renderConversation();
        clearError();
        showChatView();
        input.focus();
    };

    const showChatView = () => {
        historyView.hidden = true;
        chatView.hidden = false;
        historyButton.setAttribute('aria-label', 'Lihat riwayat percakapan');
    };

    const renderHistory = () => {
        historyList.replaceChildren();
        if (!conversations.length) {
            const empty = document.createElement('p');
            empty.className = 'ai-chat-history-empty';
            empty.textContent = 'Belum ada percakapan tersimpan. Mulai obrolan baru untuk menjelajahi cerita Nusantara.';
            historyList.append(empty);
            return;
        }

        conversations.forEach((conversation) => {
            const item = document.createElement('div');
            item.className = 'ai-chat-history-item';
            const open = document.createElement('button');
            open.className = 'ai-chat-history-open';
            open.type = 'button';
            const title = document.createElement('strong');
            title.textContent = conversation.title || 'Percakapan';
            const preview = document.createElement('small');
            preview.textContent = conversation.messages.at(-1)?.content || 'Belum ada pesan';
            open.append(title, preview);
            open.addEventListener('click', () => {
                activeConversation = conversation;
                renderConversation();
                showChatView();
            });

            const remove = document.createElement('button');
            remove.className = 'ai-chat-history-delete';
            remove.type = 'button';
            remove.setAttribute('aria-label', `Hapus ${conversation.title || 'percakapan'}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                conversations = conversations.filter((entry) => entry.id !== conversation.id);
                if (activeConversation?.id === conversation.id) {
                    activeConversation = conversations[0] || null;
                    renderConversation();
                }
                saveConversations();
                renderHistory();
            });
            item.append(open, remove);
            historyList.append(item);
        });
    };

    const openPanel = () => {
        panel.hidden = false;
        panel.classList.remove('is-minimized');
        panel.setAttribute('aria-hidden', 'false');
        openButton.setAttribute('aria-expanded', 'true');
        window.requestAnimationFrame(() => panel.classList.add('is-open'));
        window.setTimeout(() => input.focus(), 220);
    };

    const closePanel = () => {
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        openButton.setAttribute('aria-expanded', 'false');
        window.setTimeout(() => {
            if (panel.getAttribute('aria-hidden') === 'true') {
                panel.hidden = true;
            }
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 210);
    };

    const sendMessage = async (message) => {
        if (!message || sendButton.disabled) {
            return;
        }

        clearError();
        welcome.hidden = true;
        const history = activeConversation?.messages.slice(-conversationLimit) || [];
        if (!activeConversation) {
            createConversation();
        }

        activeConversation.messages.push({ role: 'user', content: message });
        if (activeConversation.title === 'Percakapan baru') {
            activeConversation.title = message.slice(0, 54);
        }
        activeConversation.updatedAt = Date.now();
        appendMessage('user', message);
        saveConversations();
        input.value = '';
        input.style.height = 'auto';
        sendButton.disabled = true;

        const typing = document.createElement('div');
        typing.className = 'ai-chat-message is-pending';
        typing.setAttribute('aria-label', 'AI sedang menulis');
        const dots = document.createElement('div');
        dots.className = 'ai-chat-bubble';
        for (let index = 0; index < 3; index += 1) {
            dots.append(document.createElement('i'));
        }
        typing.append(dots);
        messagesElement.append(typing);
        messagesElement.scrollTop = messagesElement.scrollHeight;

        try {
            const response = await fetch(assistant.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ message, history }),
            });
            const result = await response.json();
            typing.remove();

            if (!response.ok) {
                if (response.status === 422) {
                    throw new Error('Pertanyaan terlalu panjang atau format pesan tidak valid. Coba ringkas pertanyaanmu.');
                }
                if (response.status === 429) {
                    if (result.code === 'quota_exhausted' && result.message) {
                        throw new Error(result.message);
                    }
                    throw new Error('Pesan sedang terlalu sering dikirim. Tunggu sebentar lalu coba lagi.');
                }
                throw new Error(result.message || 'Pesan belum dapat dikirim. Silakan coba kembali.');
            }

            activeConversation.messages.push({ role: 'assistant', content: result.answer });
            activeConversation.updatedAt = Date.now();
            saveConversations();
            appendMessage('assistant', result.answer);
        } catch (error) {
            typing.remove();
            showError(error instanceof TypeError
                ? 'Koneksi terputus. Periksa jaringanmu lalu coba lagi.'
                : error.message);
        } finally {
            sendButton.disabled = false;
            input.focus();
        }
    };

    openButton.addEventListener('click', () => {
        if (panel.hidden) {
            openPanel();
            return;
        }
        panel.classList.remove('is-minimized');
        input.focus();
    });

    closeButton.addEventListener('click', closePanel);
    minimizeButton.addEventListener('click', () => {
        const minimized = panel.classList.toggle('is-minimized');
        minimizeButton.setAttribute('aria-label', minimized ? 'Perbesar percakapan' : 'Minimalkan percakapan');
    });
    historyButton.addEventListener('click', () => {
        renderHistory();
        chatView.hidden = true;
        historyView.hidden = false;
    });
    assistant.querySelector('[data-ai-back]').addEventListener('click', showChatView);
    assistant.querySelector('[data-ai-new-chat]').addEventListener('click', createConversation);
    assistant.querySelectorAll('[data-ai-suggestion]').forEach((button) => {
        button.addEventListener('click', () => sendMessage(button.dataset.aiSuggestion));
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value.trim());
    });
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 96)}px`;
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            closePanel();
        }
    });

    renderConversation();
}
