import { elements, el } from './dom.js';
import { renderSafeMarkdown, styleRichContent } from './markdown.js';
import { applyDirection } from './rtl.js';
import { messageBubble } from './ui.js';
import { getToken } from './auth.js';

export async function sendMessage(state, { createChatIfNeeded, loadMessages, loadChats }){
  const { messagesEl, composer, sendBtn } = elements;
  const text = composer.value.trim();
  if (!text) return;
  await createChatIfNeeded();

  messagesEl.appendChild(messageBubble('user', text));
  messagesEl.scrollTop = messagesEl.scrollHeight;
  composer.value = '';
  composer.style.height = 'auto';

  sendBtn.disabled = true;
  const prevInner = sendBtn.innerHTML; sendBtn.dataset.prev = prevInner;
  sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  try {
    const assistant = messageBubble('assistant', '');
    messagesEl.appendChild(assistant);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    const thinking = el('div','flex items-center gap-2 text-sm text-slate-500');
    thinking.innerHTML = '<span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce [animation-delay:-0.2s]"></span><span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce"></span><span class="inline-flex w-2 h-2 rounded-full bg-slate-400 animate-bounce [animation-delay:0.2s]"></span>';
    assistant._contentEl.appendChild(thinking); assistant._thinkingEl = thinking;

    const token = getToken();
    const r = await fetch('/api/messages/stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', ...(token ? { 'Authorization': `Bearer ${token}` } : {}) }, body: JSON.stringify({ chat_id: state.currentChatId, role: 'user', content: text }) });
    if (!r.ok || !r.body) throw new Error(await r.text());

    const reader = r.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let renderQueued = false;
    let smoothQueue = '';
    const scheduleRender = () => {
      if (renderQueued) return; renderQueued = true;
      requestAnimationFrame(() => {
        const maxPerFrame = 240;
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
          if (evt.delta) { if (assistant._thinkingEl) { assistant._thinkingEl.remove(); assistant._thinkingEl = null; } smoothQueue += evt.delta; scheduleRender(); }
          if (evt.done) { scheduleRender(); }
        } catch {}
      }
    }
    // Keep the streamed content in place; just refresh chats (title, etc.)
    await loadChats();
  } catch (e) {
    console.error(e); alert('Failed to send message. Check server logs.');
  } finally {
    sendBtn.disabled = false; sendBtn.innerHTML = sendBtn.dataset.prev || '<i class="fa-regular fa-paper-plane"></i>';
  }
}
