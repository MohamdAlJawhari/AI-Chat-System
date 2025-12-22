import { el } from '../core/dom.js';
import { state } from '../core/state.js';
import { applyDirection } from '../core/rtl.js';
import { renderSafeMarkdown, styleRichContent } from './markdown.js';

function downloadText(filename, text) {
  try {
    const blob = new Blob([text ?? ''], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) { console.error('Download failed', e); }
}

function timestampName() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `uchat-${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}.txt`;
}

function sanitizeTitle(s) {
  const base = String(s || 'untitled').trim().slice(0, 80);
  const noInvalid = base.replace(/[^\p{L}0-9\-_ ]/gu, '_');
  return noInvalid.replace(/\s+/g, '_') || 'untitled';
}

function buildFilename(chatTitle) {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  const ts = `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
  return `uchat-${sanitizeTitle(chatTitle)}-${ts}.txt`;
}

async function copyTextToClipboard(text) {
  // Prefer modern API on secure contexts; fallback to execCommand otherwise
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text ?? '');
      return true;
    }
  } catch (_) { /* fall through to fallback */ }

  try {
    const ta = document.createElement('textarea');
    ta.value = text ?? '';
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '-1000px';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);
    return ok;
  } catch (e) {
    console.error('Legacy copy failed', e);
    return false;
  }
}

/**
 * Create a message bubble.
 * @param {'user'|'assistant'} role
 * @param {string|null} content
 * @param {object|null} metadata
 * @param {{chatTitle?: string}} opts
 */
export function messageBubble(role, content, metadata = null, opts = {}) {
  const wrap = el('div', 'group relative flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
  if (role !== 'user') wrap.classList.add('pb-6');
  const bubble = el('div', (
    role === 'user'
      ? 'relative max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-[var(--accent)] text-white whitespace-pre-wrap'
      : 'relative max-w-[80%] text-sm px-0 py-0 bg-transparent'
  ));
  const contentEl = el('div', 'rich');
  if (role === 'user') contentEl.textContent = content ?? '';
  else { contentEl.innerHTML = renderSafeMarkdown(content ?? ''); styleRichContent(contentEl); }
  applyDirection(contentEl, content ?? '');

  const initialArchive = !!(metadata && metadata.archive_search) || (role === 'user' && !!(opts && opts.archive));
  let archiveBadge = null;
  if (role === 'assistant') {
    archiveBadge = el('span', 'mb-2 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.3em]');
    archiveBadge.innerHTML = '<i class="fa-solid fa-database text-[9px]"></i><span>Archive Answer</span>';
    archiveBadge.style.borderColor = 'rgba(52,211,153,0.45)';
    archiveBadge.style.color = 'rgba(110,231,183,0.9)';
    archiveBadge.classList.add('hidden');
    bubble.appendChild(archiveBadge);
  } else if (initialArchive) {
    archiveBadge = el('span', 'mb-1 block text-[10px] uppercase tracking-[0.3em]');
    archiveBadge.textContent = 'Archive Query';
    archiveBadge.style.color = 'rgba(255,255,255,0.75)';
    bubble.appendChild(archiveBadge);
  }

  bubble.appendChild(contentEl);

  // Overlays for assistant messages: toolbar bottom-left (latency + actions)
  if (role !== 'user') {
    const toolbar = el('div', 'absolute -bottom-5 left-0 flex items-center gap-2');
    const btnCls = 'h-6 w-6 rounded-full bg-slate-200 hover:bg-slate-300 text-slate-700 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-white flex items-center justify-center';

    const copyBtn = el('button', btnCls);
    copyBtn.title = 'Copy';
    copyBtn.innerHTML = '<i class="fa-regular fa-copy text-[12px]"></i>';
    copyBtn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const ok = await copyTextToClipboard(wrap._raw ?? '');
      if (ok) {
        const prev = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fa-solid fa-check text-[12px]"></i>';
        setTimeout(() => { copyBtn.innerHTML = prev; }, 800);
      }
    });

    const dlBtn = el('button', btnCls);
    dlBtn.title = 'Download';
    dlBtn.innerHTML = '<i class="fa-solid fa-download text-[12px]"></i>';
    dlBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      // Use the freshest chat title if available from state; fall back to initial
      let latestTitle = opts.chatTitle;
      try {
        const activeId = wrap._chatId || state.currentChatId;
        const cur = (state.chatsCache || []).find(c => c.id === activeId);
        if (cur && cur.title) latestTitle = cur.title;
      } catch (_) { /* ignore and use fallback */ }
      const fname = buildFilename(latestTitle);
      downloadText(fname, wrap._raw ?? '');
    });

    const latency = el('div', 'text-[10px] text-neutral-400/90 select-none hidden', '');
    toolbar.appendChild(latency);
    toolbar.appendChild(copyBtn);
    toolbar.appendChild(dlBtn);
    wrap.appendChild(toolbar);
    // expose for stream logic
    wrap._toolbar = toolbar;
    wrap._latencyEl = latency;
  }

  wrap.appendChild(bubble);
  wrap._contentEl = contentEl; wrap._raw = content ?? '';
  try { wrap._chatId = state.currentChatId; } catch (_) { }
  // expose bubble for stream logic (to style pending vs rendered)
  wrap._bubble = bubble;

  if (archiveBadge) {
    const toggleBadge = (flag) => {
      archiveBadge.classList.toggle('hidden', !flag);
    };
    toggleBadge(initialArchive);
    wrap._setArchiveBadge = toggleBadge;
  }

  let sourcesBox = null;
  const ensureSourcesBox = () => {
    if (!sourcesBox) {
      sourcesBox = el('div', 'mt-4 space-y-2 rounded-2xl border px-4 py-3 text-xs');
      sourcesBox.style.borderColor = 'rgba(255,255,255,0.08)';
      sourcesBox.style.background = 'color-mix(in srgb, var(--surface) 75%, transparent)';
      bubble.appendChild(sourcesBox);
    }
    return sourcesBox;
  };
  const setSources = (sources) => {
    if (!Array.isArray(sources) || !sources.length) {
      if (sourcesBox) {
        sourcesBox.classList.add('hidden');
        sourcesBox.innerHTML = '';
      }
      return;
    }
    const box = ensureSourcesBox();
    box.classList.remove('hidden');
    box.innerHTML = '';
    const title = el('div', 'text-[11px] uppercase tracking-[0.3em]');
    title.textContent = 'Archive Sources';
    title.style.color = 'rgba(255,255,255,0.6)';
    box.appendChild(title);
    const list = el('ol', 'list-decimal space-y-2 pl-4');
    sources.forEach((src) => {
      const item = el('li', 'text-[11px] leading-relaxed');
      const heading = el('div', 'font-semibold');
      heading.textContent = `[${src.index ?? '?'}] ${src.title ?? 'Untitled dispatch'}`;
      heading.style.color = 'var(--text)';
      item.appendChild(heading);
      const meta = el('div', 'text-[10px]');
      const parts = [];
      if (src.news_id) parts.push(`ID ${src.news_id}`);
      if (typeof src.score === 'number') parts.push(`score ${Number(src.score).toFixed(3)}`);
      meta.textContent = parts.join(' · ');
      meta.style.color = 'rgba(255,255,255,0.6)';
      item.appendChild(meta);
      const snippet = src.snippet || src.introduction || src.body_excerpt;
      if (snippet) {
        const sn = el('p', 'mt-1 text-[11px]');
        sn.textContent = snippet;
        sn.style.color = 'rgba(255,255,255,0.85)';
        item.appendChild(sn);
      }
      list.appendChild(item);
    });
    box.appendChild(list);
  };
  wrap._setSources = setSources;
  if (metadata && Array.isArray(metadata.sources)) {
    setSources(metadata.sources);
  }

  return wrap;
}

/** Create a chat list row with select/rename/delete actions. */
export function chatItem(chat, { onSelect, onRename, onDelete, active }) {
  /**
   * UI widgets: messageBubble + chatItem.
   * - messageBubble supports user/assistant styles, streaming, and an external toolbar
   *   with time + copy + download for assistant messages.
   */
  const row = el('div', 'group flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-neutral-800');
  if (active) row.classList.add('bg-slate-200', 'dark:bg-neutral-800');
  const btn = el('button', 'flex-1 text-left px-1 py-1'); btn.textContent = chat.title || 'Untitled'; btn.onclick = () => onSelect(chat.id);
  const rename = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); rename.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>'; rename.title = 'Rename chat'; rename.onclick = (e) => { e.stopPropagation(); onRename(chat); };
  const del = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); del.innerHTML = '<i class="fa-solid fa-trash"></i>'; del.title = 'Delete chat'; del.onclick = (e) => { e.stopPropagation(); onDelete(chat); };
  row.append(btn, rename, del);
  return row;
}
