<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title> UChat</title>
    <!-- Tailwind (CDN for dev) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        bg: {
                            DEFAULT: '#0b0b0d',
                            soft: '#111113',
                        },
                        panel: '#0f1012',
                    }
                }
            }
        }
    </script>
</head>

<body class="h-screen bg-bg text-gray-100">
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside class="w-64 bg-panel border-r border-neutral-800 p-4 flex flex-col">
            <div class="text-xl font-semibold mb-4">UChat</div>
            <button id="newChatBtn" class="mb-3 rounded-xl bg-neutral-800 hover:bg-neutral-700 px-3 py-2 text-sm">+ New
                Chat</button>
            <div class="text-xs text-neutral-400 uppercase tracking-wide mb-2">Chats</div>
            <div id="chatList" class="space-y-1 overflow-y-auto grow"></div>
            <div class="pt-4 text-xs text-neutral-500">© UChat (dev)</div>
        </aside>


        <!-- Main -->
        <main class="flex-1 flex flex-col">
            <!-- Top bar -->
            <header class="p-3 border-b border-neutral-800 flex items-center justify-end gap-2">
                <label class="text-sm text-neutral-400">Model</label>
                <select id="modelSelect" class="bg-neutral-900 border border-neutral-700 rounded-md px-3 py-1 text-sm">
                    <option value="gpt-oss:20b">gpt-oss:20b</option>
                    <option value="llama3.1:8b">llama3.1:8b</option>
                    <option value="qwen2.5:7b">qwen2.5:7b</option>
                </select>
            </header>


            <!-- Messages -->
            <section id="messages" class="flex-1 overflow-y-auto p-6 space-y-4"></section>


            <!-- Composer -->
            <footer class="p-4 border-t border-neutral-800">
                <div class="flex items-end gap-2 md:flex-row flex-col">
                    <textarea id="composer" rows="2" placeholder="Type a message..." class="flex-1 min-h-[44px] rounded-xl bg-neutral-900 border border-neutral-700 p-3 text-sm
             focus:outline-none focus:ring-1 focus:ring-neutral-600"></textarea>

                    <button id="sendBtn"
                        class="md:w-auto w-full rounded-xl bg-white/90 text-black px-4 py-2 text-sm font-medium hover:bg-white">
                        Send ⏎
                    </button>
                </div>
                <div class="text-xs text-neutral-500 mt-2">Enter to send • Shift+Enter for newline</div>
            </footer>
        </main>
    </div>

    <script>
        const chatListEl = document.getElementById('chatList');
        const modelSelect = document.getElementById('modelSelect');
        const messagesEl = document.getElementById('messages');
        const composer = document.getElementById('composer');
        const newChatBtn = document.getElementById('newChatBtn');
        const sendBtn = document.getElementById('sendBtn');

        let currentChatId = null;

        function el(tag, cls = '', text = '') {
            const e = document.createElement(tag);
            if (cls) e.className = cls;
            if (text) e.textContent = text;
            return e;
        }

        function messageBubble(role, content) {
            const wrap = el('div', 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
            const bubble = el('div',
                'max-w-[80%] whitespace-pre-wrap rounded-2xl px-4 py-2 text-sm ' +
                (role === 'user' ? 'bg-blue-600 text-white' : 'bg-neutral-800 border border-neutral-700')
            );
            bubble.textContent = content ?? '';
            wrap.appendChild(bubble);
            return wrap;
        }

        function chatItem(chat) {
            const item = el('button', 'w-full text-left px-3 py-2 rounded-lg hover:bg-neutral-800');
            item.textContent = chat.title || 'Untitled';
            item.onclick = () => selectChat(chat.id);
            if (chat.id === currentChatId) item.classList.add('bg-neutral-800');
            return item;
        }

        async function apiGet(path) {
            const r = await fetch(path, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error(await r.text());
            return r.json();
        }

        async function apiPost(path, body) {
            const r = await fetch(path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body ?? {})
            });
            if (!r.ok) throw new Error(await r.text());
            return r.json();
        }

        async function loadChats() {
            const chats = await apiGet('/api/chats');
            chatListEl.innerHTML = '';
            chats.forEach(c => chatListEl.appendChild(chatItem(c)));
            if (!currentChatId && chats[0]) {
                currentChatId = chats[0].id;
                await loadMessages();
            }
        }

        async function selectChat(id) {
            currentChatId = id;
            await loadMessages();
            await loadChats(); // refresh highlight
        }

        async function loadMessages() {
            if (!currentChatId) { messagesEl.innerHTML = ''; return; }
            const msgs = await apiGet(`/api/chats/${currentChatId}/messages`);
            messagesEl.innerHTML = '';
            if (msgs.length === 0) {
                messagesEl.appendChild(el('div', 'text-neutral-500 text-center mt-20', 'Say hello to start the conversation.'));
            }
            msgs.forEach(m => messagesEl.appendChild(messageBubble(m.role, m.content)));
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        async function createChatIfNeeded() {
            if (currentChatId) return currentChatId;
            const chat = await apiPost('/api/chats', {
                title: 'New chat',
                settings: { model: modelSelect.value }
            });
            currentChatId = chat.id;
            await loadChats();
            return currentChatId;
        }

        async function sendMessage() {
            const text = composer.value.trim();
            if (!text) return;
            await createChatIfNeeded();

            // optimistic UI
            messagesEl.appendChild(messageBubble('user', text));
            messagesEl.scrollTop = messagesEl.scrollHeight;
            composer.value = '';

            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending…';
            try {
                await apiPost('/api/messages', {
                    chat_id: currentChatId,
                    role: 'user',
                    content: text
                });
                await loadMessages();
            } catch (e) {
                console.error(e);
                alert('Failed to send message. Check server logs.');
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send ⏎';
            }
        }

        newChatBtn.addEventListener('click', async () => {
            currentChatId = null; // force a new chat with the selected model
            await createChatIfNeeded();
            messagesEl.innerHTML = '';
        });

        sendBtn.addEventListener('click', sendMessage);

        composer.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // If you change the model, the next send will create a new chat using that model
        modelSelect.addEventListener('change', () => {
            currentChatId = null;
        });

        // init
        (async () => { await loadChats(); })();
    </script>

</body>

</html>