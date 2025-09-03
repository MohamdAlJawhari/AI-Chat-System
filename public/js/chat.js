// UChat — client UI logic
(() => {
  const chatListEl = document.getElementById('chatList');
  const modelSelect = document.getElementById('modelSelect');
  const messagesEl = document.getElementById('messages');
  const composer = document.getElementById('composer');
  const newChatBtn = document.getElementById('newChatBtn');
  const sendBtn = document.getElementById('sendBtn');
  const sidebar = document.getElementById('sidebar');
  const divider = document.getElementById('sidebarDivider');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarIcon = document.getElementById('sidebarIcon');
  const themeToggle = document.getElementById('themeToggle');

  let currentChatId = null;
  let chatsCache = [];
  let isDragging = false;
  let sidebarHiddenState = false;
  const sidebarPadDefault = sidebar ? window.getComputedStyle(sidebar).padding : '16px';
  const sidebarBorderRDefault = sidebar ? window.getComputedStyle(sidebar).borderRightWidth : '1px';

  function el(tag, cls = '', text = '') {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text) e.textContent = text;
    return e;
  }

  function escapeHtml(s) {
    return (s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Basic RTL detection for Arabic/Hebrew text
  function detectDirection(text){
    const s = String(text || '');
    const rtlMatch = s.match(/[\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/g);
    const ltrMatch = s.match(/[A-Za-z]/g);
    const rtlCount = rtlMatch ? rtlMatch.length : 0;
    const ltrCount = ltrMatch ? ltrMatch.length : 0;
    if (rtlCount === 0 && ltrCount === 0) return 'ltr';
    return rtlCount >= ltrCount ? 'rtl' : 'ltr';
  }

  function applyDirection(el, text){
    const dir = detectDirection(text);
    el.setAttribute('dir', dir);
    el.style.unicodeBidi = 'plaintext';
    el.classList.toggle('text-right', dir === 'rtl');
    el.classList.toggle('text-left', dir !== 'rtl');
  }

  function convertMarkdownTables(md) {
    const lines = (md ?? '').split(/\r?\n/);
    const out = [];
    for (let i = 0; i < lines.length; i++) {
      const h = lines[i];
      const sep = lines[i + 1] ?? '';
      if (/^\s*\|.*\|\s*$/.test(h) && /^\s*\|?\s*[:\-\s|]+\s*$/.test(sep)) {
        const headers = h.trim().replace(/^\||\|$/g, '').split('|').map(s => s.trim());
        i += 1;
        const rows = [];
        while (i + 1 < lines.length && /^\s*\|.*\|\s*$/.test(lines[i + 1])) {
          const row = lines[i + 1].trim().replace(/^\||\|$/g, '').split('|').map(s => s.trim());
          rows.push(row); i += 1;
        }
        let html = '<table><thead><tr>' + headers.map(hc => `<th>${escapeHtml(hc)}</th>`).join('') + '</tr></thead><tbody>';
        for (const r of rows) { html += '<tr>' + r.map(c => `<td>${escapeHtml(c)}</td>`).join('') + '</tr>'; }
        html += '</tbody></table>';
        out.push(html);
      } else {
        out.push(escapeHtml(h));
      }
    }
    return out.join('\n');
  }

  function renderSafeMarkdown(text) {
    try {
      if (window.marked) {
        const renderer = new marked.Renderer();
        renderer.code = (code, infostring) => {
          const lang = (infostring || '').trim().toLowerCase();
          if (lang === 'mermaid') {
            return `<div class="mermaid">${escapeHtml(code)}</div>`;
          }
          return `<pre><code>${escapeHtml(code)}</code></pre>`;
        };
        const html = marked.parse(text ?? '', { gfm: true, breaks: true, renderer });
        return DOMPurify.sanitize(html, {
          ADD_TAGS: ['table', 'thead', 'tbody', 'tr', 'td', 'th', 'pre', 'code', 'span', 'div'],
          ADD_ATTR: ['class', 'href', 'title', 'target', 'rel', 'colspan', 'rowspan', 'align']
        });
      }
      const converted = convertMarkdownTables(text ?? '');
      return DOMPurify.sanitize(converted, {
        ADD_TAGS: ['table', 'thead', 'tbody', 'tr', 'td', 'th'],
        ADD_ATTR: []
      });
    } catch { return escapeHtml(text); }
  }

  function styleRichContent(container) {
    container.querySelectorAll('table').forEach(t => {
      t.classList.add('w-full', 'text-sm', 'border-collapse', 'my-2');
      t.querySelectorAll('th,td').forEach(cell => cell.classList.add('border', 'border-slate-300', 'dark:border-neutral-700', 'px-2', 'py-1', 'align-top'));
      t.querySelectorAll('thead').forEach(th => th.classList.add('bg-slate-100', 'dark:bg-neutral-900'));
    });
    container.querySelectorAll('pre').forEach(pre => pre.classList.add('bg-slate-100', 'dark:bg-neutral-900', 'border', 'border-slate-300', 'dark:border-neutral-700', 'rounded-lg', 'p-3', 'overflow-x-auto', 'my-2'));
    container.querySelectorAll('code').forEach(code => code.classList.add('bg-slate-100', 'dark:bg-neutral-900', 'rounded', 'px-1', 'py-0.5'));
    container.querySelectorAll('blockquote').forEach(bq => bq.classList.add('border-l-4', 'border-slate-300', 'dark:border-neutral-700', 'pl-3', 'text-slate-700', 'dark:text-neutral-300', 'my-2'));
    container.querySelectorAll('ul').forEach(ul => ul.classList.add('list-disc', 'pl-5', 'my-2'));
    container.querySelectorAll('ol').forEach(ol => ol.classList.add('list-decimal', 'pl-5', 'my-2'));
    container.querySelectorAll('a').forEach(a => { a.classList.add('text-blue-600', 'dark:text-blue-400', 'underline', 'hover:opacity-90'); a.target = '_blank'; a.rel = 'noopener'; });
    if (window.mermaid && container.querySelector('.mermaid')) {
      try { mermaid.init(undefined, container.querySelectorAll('.mermaid')); } catch (e) { }
    }
  }

  function messageBubble(role, content, metadata = null) {
    const wrap = el('div', 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
    const bubble = el('div', 'max-w-[80%] rounded-2xl px-4 py-2 text-sm ' + (role === 'user' ? 'bg-blue-600 text-white whitespace-pre-wrap' : 'bg-slate-100 border border-slate-300 dark:bg-neutral-800 dark:border-neutral-700'));
    const contentEl = el('div', 'rich');
    if (role === 'user') {
      contentEl.textContent = content ?? '';
    } else {
      const html = renderSafeMarkdown(content ?? '');
      contentEl.innerHTML = html;
      styleRichContent(contentEl);
    }
    applyDirection(contentEl, content ?? '');
    bubble.appendChild(contentEl);
    if (role !== 'user' && metadata && (metadata.model || metadata.tokens)) {
      const m = [];
      if (metadata.model) m.push(`model: ${metadata.model}`);
      if (metadata.tokens) m.push(`tokens p:${metadata.tokens.prompt ?? ''} c:${metadata.tokens.completion ?? ''}`);
      const metaEl = el('div', 'text-[10px] mt-1 text-neutral-400', m.join(' • '));
      metaEl.setAttribute('dir','ltr');
      bubble.appendChild(metaEl);
    }
    wrap.appendChild(bubble);
    wrap._contentEl = contentEl;
    wrap._raw = content ?? '';
    return wrap;
  }

  // Sidebar resizing and toggle
  function setSidebarWidth(px){
    const min = 160, max = 480;
    const clamped = Math.max(min, Math.min(max, px));
    document.documentElement.style.setProperty('--sidebar-w', clamped + 'px');
    localStorage.setItem('sidebarW', String(clamped));
  }
  function restoreSidebarPrefs(){
    const w = parseInt(localStorage.getItem('sidebarW') || '0', 10);
    if (w) document.documentElement.style.setProperty('--sidebar-w', w + 'px');
    const hidden = localStorage.getItem('sidebarHidden') === '1';
    sidebarHiddenState = hidden;
    hideSidebar(hidden);
  }
  function hideSidebar(hidden){
    sidebarHiddenState = hidden;
    if (hidden){
      // animate to width 0 for smooth closing
      document.documentElement.style.setProperty('--sidebar-w', '0px');
      if (sidebar) sidebar.style.pointerEvents = 'none';
      if (sidebar) sidebar.style.padding = '0px';
      if (sidebar) sidebar.style.borderRightWidth = '0px';
      if (divider) divider.style.display = 'none';
    } else {
      const w = parseInt(localStorage.getItem('sidebarW') || '256', 10) || 256;
      document.documentElement.style.setProperty('--sidebar-w', w + 'px');
      if (sidebar) sidebar.style.pointerEvents = '';
      if (sidebar) sidebar.style.padding = sidebarPadDefault;
      if (sidebar) sidebar.style.borderRightWidth = sidebarBorderRDefault;
      if (divider) divider.style.display = '';
    }
    localStorage.setItem('sidebarHidden', hidden ? '1' : '0');
    if (sidebarIcon){
      sidebarIcon.className = hidden ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
    }
  }
  if (divider){
    divider.addEventListener('mousedown', (e)=>{ isDragging = true; document.body.classList.add('select-none'); });
    window.addEventListener('mouseup', ()=>{ isDragging = false; document.body.classList.remove('select-none'); });
    window.addEventListener('mousemove', (e)=>{
      if (!isDragging || !sidebar) return;
      setSidebarWidth(e.clientX);
    });
  }
  if (sidebarToggle){
    sidebarToggle.addEventListener('click', ()=>{
      hideSidebar(!sidebarHiddenState);
    });
  }

  // Theme toggle
  function setTheme(mode){
    if (mode === 'dark') document.documentElement.classList.add('dark');
    else document.documentElement.classList.remove('dark');
    localStorage.setItem('theme', mode);
    const icon = document.getElementById('themeIcon') || (themeToggle ? themeToggle.querySelector('i') : null);
    if (icon) {
      if (mode === 'dark') {
        icon.className = 'fa-solid fa-moon';
      } else {
        icon.className = 'fa-notdog fa-solid fa-sun';
      }
    }
    if (window.mermaid){
      try{ mermaid.initialize({ startOnLoad: false, theme: mode === 'dark' ? 'dark' : 'default' }); }catch(e){}
    }
  }
  function initTheme(){
    const t = localStorage.getItem('theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const mode = t || (prefersDark ? 'dark' : 'light');
    setTheme(mode);
  }
  if (themeToggle){
    themeToggle.addEventListener('click', ()=>{
      const isDark = document.documentElement.classList.contains('dark');
      setTheme(isDark ? 'light' : 'dark');
    });
  }

  function chatItem(chat) {
    const row = el('div', 'group flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-neutral-800');
    if (chat.id === currentChatId) row.classList.add('bg-neutral-800');
    const btn = el('button', 'flex-1 text-left px-1 py-1');
    btn.textContent = chat.title || 'Untitled';
    btn.onclick = () => selectChat(chat.id);

    const rename = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block');
    // Use widely-supported FA solid icons
    rename.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>';
    rename.title = 'Rename chat';
    rename.onclick = async (e) => {
      e.stopPropagation();
      const newTitle = prompt('Rename chat', chat.title || '');
      if (newTitle === null) return;
      try {
        await fetch(`/api/chats/${chat.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ title: newTitle }) });
        await loadChats();
      } catch (err) { console.error('Rename failed', err); }
    };

    const del = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block');
    del.innerHTML = '<i class="fa-solid fa-trash"></i>';
    del.title = 'Delete chat';
    del.onclick = async (e) => {
      e.stopPropagation();
      if (!confirm('Delete this chat?')) return;
      try {
        await fetch(`/api/chats/${chat.id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } });
        if (currentChatId === chat.id) { currentChatId = null; messagesEl.innerHTML = ''; }
        await loadChats();
      } catch (err) { console.error('Delete failed', err); }
    };

    row.appendChild(btn);
    row.appendChild(rename);
    row.appendChild(del);
    return row;
  }

  async function apiGet(path) {
    const r = await fetch(path, { headers: { 'Accept': 'application/json' } });
    if (!r.ok) throw new Error(await r.text());
    return r.json();
  }

  async function apiPost(path, body) {
    const r = await fetch(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(body ?? {}) });
    if (!r.ok) throw new Error(await r.text());
    return r.json();
  }

  async function loadChats() {
    const chats = await apiGet('/api/chats');
    chatListEl.innerHTML = '';
    chats.forEach(c => chatListEl.appendChild(chatItem(c)));
    chatsCache = chats;
    if (!currentChatId && chats[0]) {
      currentChatId = chats[0].id;
      const cur = chats[0];
      if (cur?.settings?.model && [...modelSelect.options].some(o => o.value === cur.settings.model)) {
        modelSelect.value = cur.settings.model;
      }
      await loadMessages();
    }
    return chats;
  }

  async function selectChat(id) {
    currentChatId = id;
    await loadMessages();
    const chats = await loadChats();
    const cur = (chats || chatsCache).find(c => c.id === currentChatId);
    if (cur?.settings?.model && [...modelSelect.options].some(o => o.value === cur.settings.model)) {
      modelSelect.value = cur.settings.model;
    }
  }

  async function loadMessages() {
    if (!currentChatId) { messagesEl.innerHTML = ''; return; }
    const msgs = await apiGet(`/api/chats/${currentChatId}/messages`);
    messagesEl.innerHTML = '';
    if (msgs.length === 0) {
      messagesEl.appendChild(renderEmptyState());
    }
    msgs.forEach(m => messagesEl.appendChild(messageBubble(m.role, m.content, m.metadata)));
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function renderEmptyState(){
    const card = el('div','mx-auto max-w-3xl mt-10');
    card.innerHTML = `
      <div class="rounded-2xl bg-white/80 dark:bg-neutral-800/80 backdrop-blur border border-slate-200 dark:border-neutral-700 px-6 py-8 shadow-md">
        <div class="text-center mb-6">
          <div class="mx-auto w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-500 mb-3">
            <i class="fa-solid fa-robot"></i>
          </div>
          <h2 class="text-2xl font-semibold">How can I help you today?</h2>
          <p class="text-sm text-slate-600 dark:text-neutral-400">Start by asking a question or choose a quick action below.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="rounded-xl border border-slate-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4">
            <div class="text-emerald-500 mb-2"><i class="fa-regular fa-floppy-disk"></i></div>
            <div class="font-medium">Saved Prompt Templates</div>
            <div class="text-xs text-slate-600 dark:text-neutral-400">Reuse prompts for faster responses.</div>
          </div>
          <div class="rounded-xl border border-slate-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4">
            <div class="text-emerald-500 mb-2"><i class="fa-regular fa-images"></i></div>
            <div class="font-medium">Media Type Selection</div>
            <div class="text-xs text-slate-600 dark:text-neutral-400">Pick a media type to tailor replies.</div>
          </div>
          <div class="rounded-xl border border-slate-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 p-4">
            <div class="text-emerald-500 mb-2"><i class="fa-solid fa-language"></i></div>
            <div class="font-medium">Multilingual Support</div>
            <div class="text-xs text-slate-600 dark:text-neutral-400">Choose language for better interaction.</div>
          </div>
        </div>
      </div>`;
    return card;
  }

  async function createChatIfNeeded() {
    if (currentChatId) return currentChatId;
    const chat = await apiPost('/api/chats', { settings: { model: modelSelect.value } });
    currentChatId = chat.id;
    await loadChats();
    return currentChatId;
  }

  async function sendMessage() {
    const text = composer.value.trim();
    if (!text) return;
    await createChatIfNeeded();

    messagesEl.appendChild(messageBubble('user', text));
    messagesEl.scrollTop = messagesEl.scrollHeight;
    composer.value = '';
    if (composer){ composer.style.height = 'auto'; }

    sendBtn.disabled = true;
    const prevInner = sendBtn.innerHTML;
    sendBtn.dataset.prev = prevInner;
    sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    try {
      const assistant = messageBubble('assistant', '');
      messagesEl.appendChild(assistant);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      // show thinking indicator until first delta arrives
      const thinking = el('div','flex items-center gap-2 text-sm text-slate-500');
      thinking.innerHTML = '<span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce [animation-delay:-0.2s]"></span><span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce"></span><span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce [animation-delay:0.2s]"></span>';
      assistant._contentEl.appendChild(thinking);
      assistant._thinkingEl = thinking;

      const r = await fetch('/api/messages/stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' }, body: JSON.stringify({ chat_id: currentChatId, role: 'user', content: text }) });
      if (!r.ok || !r.body) throw new Error(await r.text());

      const reader = r.body.getReader();
      const decoder = new TextDecoder();
  let buffer = '';
  let renderQueued = false;
  let smoothQueue = '';
  let animating = false;
  const scheduleRender = () => {
    if (renderQueued) return;
    renderQueued = true;
    requestAnimationFrame(() => {
      // Move a portion of the queue into the raw text for smoother flow
      const maxPerFrame = 240; // characters per frame (approx)
      const chunk = smoothQueue.slice(0, maxPerFrame);
      smoothQueue = smoothQueue.slice(maxPerFrame);
      if (chunk) assistant._raw += chunk;
      const html = renderSafeMarkdown(assistant._raw);
      assistant._contentEl.innerHTML = html;
      styleRichContent(assistant._contentEl);
      applyDirection(assistant._contentEl, assistant._raw);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      renderQueued = false;
      if (smoothQueue.length) scheduleRender();
    });
  };
      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        let idx;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
          const chunk = buffer.slice(0, idx).trim();
          buffer = buffer.slice(idx + 2);
          if (!chunk.startsWith('data:')) continue;
          const json = chunk.slice(5).trim();
          try {
            const evt = JSON.parse(json);
            if (evt.delta) {
              if (assistant._thinkingEl) { assistant._thinkingEl.remove(); assistant._thinkingEl = null; }
              smoothQueue += evt.delta;
              scheduleRender();
            }
            if (evt.done) { scheduleRender(); }
          } catch { }
        }
      }
      await loadMessages();
      await loadChats();
    } catch (e) {
      console.error(e);
      alert('Failed to send message. Check server logs.');
    } finally {
      sendBtn.disabled = false;
      sendBtn.innerHTML = sendBtn.dataset.prev || '<i class="fa-regular fa-paper-plane"></i>';
    }
  }

  newChatBtn.addEventListener('click', async () => {
    currentChatId = null;
    await createChatIfNeeded();
    messagesEl.innerHTML = '';
  });

  sendBtn.addEventListener('click', sendMessage);

  composer.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });
  // adjust composer direction on input for RTL/LTR
  function updateComposerDir(){ applyDirection(composer, composer.value);
    // auto-resize textarea for a nicer input feel
    composer.style.height = 'auto';
    const maxH = 200; // px
    composer.style.height = Math.min(maxH, composer.scrollHeight) + 'px';
  }
  composer.addEventListener('input', updateComposerDir);
  updateComposerDir();

  modelSelect.addEventListener('change', async () => {
    if (currentChatId) {
      try {
        await fetch(`/api/chats/${currentChatId}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ settings: { model: modelSelect.value } }) });
        await loadChats();
      } catch (e) { console.error('Failed to update chat model', e); }
    }
  });

  // Quick new chat shortcut: Ctrl/Cmd + N
  window.addEventListener('keydown', async (e) => {
    const key = e.key ? e.key.toLowerCase() : '';
    if ((e.ctrlKey || e.metaKey) && key === 'n') {
      e.preventDefault();
      currentChatId = null;
      await createChatIfNeeded();
      await loadMessages();
      await loadChats();
    }
  });

  (async () => {
    restoreSidebarPrefs();
    initTheme();
    try {
      const models = await apiGet('/api/models');
      if (Array.isArray(models) && models.length) {
        modelSelect.innerHTML = '';
        for (const m of models) { const opt = document.createElement('option'); opt.value = m; opt.textContent = m; modelSelect.appendChild(opt); }
      } else { throw new Error('no models'); }
    } catch {
      modelSelect.innerHTML = '';
      ['gpt-oss:20b', 'openchat'].forEach(m => { const opt = document.createElement('option'); opt.value = m; opt.textContent = m; modelSelect.appendChild(opt); });
    }
    await loadChats();
  })();
})();
