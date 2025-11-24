import { elements, el } from '../core/dom.js';
import { renderSafeMarkdown, styleRichContent } from '../ui/markdown.js';
import { applyDirection } from '../core/rtl.js';
import { messageBubble } from '../ui/ui.js';

export async function sendMessage(state, { createChatIfNeeded, loadMessages, loadChats }){
  const { messagesEl, composer, sendBtn } = elements;
  const text = composer.value.trim();
  if (!text) return;
  await createChatIfNeeded();
  const usingArchive = !!state.archiveEnabled;

  const cur = (state.chatsCache || []).find(c=>c.id===state.currentChatId);
  const chatTitle = cur?.title || 'Untitled';
  messagesEl.appendChild(messageBubble('user', text, usingArchive ? { archive_search: true } : null, { chatTitle }));
  messagesEl.scrollTop = messagesEl.scrollHeight;
  composer.value = '';
  composer.style.height = 'auto';

  sendBtn.disabled = true;
  const prevInner = sendBtn.innerHTML; sendBtn.dataset.prev = prevInner;
  sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
  try {
    const assistant = messageBubble('assistant', '', null, { chatTitle });
    messagesEl.appendChild(assistant);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Hide toolbar while waiting for first token
    if (assistant._toolbar){ assistant._toolbar.style.display = 'none'; }

    // Loader + timer until first token arrives
    const waitStart = performance.now();
    const thinking = el('div','inline-block');
    const loaderBox = el('div','relative inline-block align-middle three-body-wrap');
    loaderBox.innerHTML = '<div class="three-body"><div class="three-body__dot"></div><div class="three-body__dot"></div><div class="three-body__dot"></div></div>';
    const timer = el('div','three-body-timer select-none','0.0s');
    loaderBox.appendChild(timer);
    thinking.appendChild(loaderBox);
    assistant._contentEl.appendChild(thinking);
    assistant._thinkingEl = thinking;
    assistant._waitTimer = setInterval(()=>{
      const s = (performance.now() - waitStart)/1000;
      timer.textContent = s.toFixed(1) + 's';
    }, 100);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const r = await fetch('/api/messages/stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ chat_id: state.currentChatId, role: 'user', content: text, archive_search: usingArchive }) });
    if (!r.ok || !r.body) throw new Error(await r.text());

    const reader = r.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let renderQueued = false;
    let smoothQueue = '';
    // Batch DOM writes for smoother rendering while streaming
    const scheduleRender = () => {
      if (renderQueued) return; renderQueued = true;
      requestAnimationFrame(() => {
        const maxPerFrame = 80;
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
          if (evt.rag_sources) {
            assistant._ragSources = evt.rag_sources;
            if (typeof assistant._setSources === 'function') {
              assistant._setSources(evt.rag_sources);
            }
          }
          if (Object.prototype.hasOwnProperty.call(evt, 'archive_search')) {
            assistant._archiveEnabled = !!evt.archive_search;
            if (typeof assistant._setArchiveBadge === 'function') {
              assistant._setArchiveBadge(assistant._archiveEnabled);
            }
          }
          if (evt.delta) {
            if (assistant._thinkingEl) {
              assistant._thinkingEl.remove(); assistant._thinkingEl = null;
              if (assistant._waitTimer) { clearInterval(assistant._waitTimer); assistant._waitTimer = null; }
            }
            // Show toolbar on first token
            if (assistant._toolbar){ assistant._toolbar.style.display = ''; }
            // Show first-token latency outside the bubble (top-left)
            if (assistant._latencyEl){
              const s = (performance.now() - waitStart)/1000;
              assistant._latencyEl.textContent = s.toFixed(1) + 's';
              assistant._latencyEl.classList.remove('hidden');
            }
            smoothQueue += evt.delta; scheduleRender();
          }
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
/**
 * Streaming send: persists the user message, shows a loader with first-token timer,
 * then incrementally renders assistant tokens from a server-sent event stream.
 *
 * Notes:
 * - While waiting for the first token we hide the toolbar and show the 3-dot loader.
 * - On first token we restore the assistant UI and reveal the toolbar with latency.
 */
}
