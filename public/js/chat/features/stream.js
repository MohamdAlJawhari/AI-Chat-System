import { elements, el } from '../core/dom.js';
import { renderSafeMarkdown, styleRichContent } from '../ui/markdown.js';
import { applyDirection } from '../core/rtl.js';
import { messageBubble } from '../ui/ui.js';

function collectArchiveFilters(){
  const panel = document.querySelector('.chat-control-panel');
  if (!panel) return {};
  const grab = (name) => {
    const input = panel.querySelector(`[name="${name}"]`);
    if (!input) return '';
    const value = (input.value ?? '').toString();
    return value.trim();
  };
  const raw = {
    category: grab('category'),
    country: grab('country'),
    city: grab('city'),
    date_from: grab('date_from'),
    date_to: grab('date_to'),
    is_breaking_news: grab('is_breaking_news'),
  };
  const cleaned = {};
  Object.entries(raw).forEach(([k, v]) => {
    if (v !== '') cleaned[k] = v;
  });
  return cleaned;
}

export async function sendMessage(state, { createChatIfNeeded, loadMessages, loadChats }){
  const { messagesEl, composer, sendBtn } = elements;
  const text = composer.value.trim();
  if (!text) return;
  await createChatIfNeeded();
  const usingArchive = !!state.archiveEnabled;
  const archiveFilters = usingArchive ? collectArchiveFilters() : {};

  const cur = (state.chatsCache || []).find(c=>c.id===state.currentChatId);
  const chatTitle = cur?.title || 'Untitled';
  const userMeta = usingArchive ? { archive_search: true, archive_filters: archiveFilters } : null;
  messagesEl.appendChild(messageBubble('user', text, userMeta, { chatTitle }));
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

    const statusBox = el('div','mt-1 flex items-center gap-2 select-none');

    const phrases = ['Hmmm...','Let me see...','Almost there...','On it...','One sec...'];
    let phraseIdx = Math.floor(Math.random() * phrases.length);
    const phraseEl = el('div','text-[12px] font-semibold tracking-wide', phrases[phraseIdx]);
    phraseEl.style.color = 'rgba(200, 200, 200, 0.29)';

    const requestedPersona = (elements.personaSelect && elements.personaSelect.value) ? elements.personaSelect.value : '';
    let personaTag = null;
    const setPersonaTag = (label, reason='') => {
      const cleanLabel = (label || '').trim();
      if (!cleanLabel) return;
      if (!personaTag) {
        personaTag = el('div','text-[11px] uppercase tracking-[0.2em]');
        personaTag.style.color = 'rgba(140,167,255,0.9)';
        statusBox.prepend(personaTag);
      }
      personaTag.textContent = cleanLabel;
      personaTag.title = reason || '';
    };
    const personaLabel = requestedPersona ? requestedPersona.replace(/_/g,' ') : '';
    if (personaLabel) {
      const initialLabel = requestedPersona === 'auto' ? 'Auto' : personaLabel;
      setPersonaTag(initialLabel);
    }

    const timer = el('div','three-body-timer text-[12px]', '0.0s');
    timer.style.color = 'rgba(195,209,231,0.9)';

    statusBox.appendChild(phraseEl);
    statusBox.appendChild(timer);
    loaderBox.appendChild(statusBox);

    thinking.appendChild(loaderBox);
    assistant._contentEl.appendChild(thinking);
    assistant._thinkingEl = thinking;
    assistant._waitTimer = setInterval(()=>{
      const s = (performance.now() - waitStart)/1000;
      timer.textContent = s.toFixed(1) + 's';
      if (s >= 1 && s < 4 && phraseIdx !== 1) { phraseIdx = 1; phraseEl.textContent = phrases[1]; }
      else if (s >= 4 && s < 9 && phraseIdx !== 2) { phraseIdx = 2; phraseEl.textContent = phrases[2]; }
      else if (s >= 9 && phraseIdx !== 3) { phraseIdx = 3; phraseEl.textContent = phrases[3]; }
    }, 100);

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const payload = { chat_id: state.currentChatId, role: 'user', content: text, archive_search: usingArchive };
    if (usingArchive && Object.keys(archiveFilters).length) {
      payload.filters = archiveFilters;
    }
    const r = await fetch('/api/messages/stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify(payload) });
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
          if (evt.persona) {
            const resolvedLabel = evt.persona.replace(/_/g,' ');
            const label = evt.persona_auto ? `Auto → ${resolvedLabel}` : resolvedLabel;
            const reason = evt.persona_reason || '';
            setPersonaTag(label, reason);
            assistant._persona = evt.persona;
            assistant._personaRequested = evt.persona_requested;
            assistant._personaReason = reason;
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
