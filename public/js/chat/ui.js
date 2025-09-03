import { el } from './dom.js';
import { applyDirection } from './rtl.js';
import { renderSafeMarkdown, styleRichContent } from './markdown.js';

export function messageBubble(role, content, metadata = null) {
  const wrap = el('div', 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
  const bubble = el('div', 'max-w-[80%] rounded-2xl px-4 py-2 text-sm ' + (role === 'user' ? 'bg-blue-600 text-white whitespace-pre-wrap' : 'bg-slate-100 border border-slate-300 dark:bg-neutral-800 dark:border-neutral-700'));
  const contentEl = el('div', 'rich');
  if (role === 'user') contentEl.textContent = content ?? '';
  else { contentEl.innerHTML = renderSafeMarkdown(content ?? ''); styleRichContent(contentEl); }
  applyDirection(contentEl, content ?? '');
  bubble.appendChild(contentEl);
  if (role !== 'user' && metadata && (metadata.model || metadata.tokens)) {
    const m = [];
    if (metadata.model) m.push(`model: ${metadata.model}`);
    if (metadata.tokens) m.push(`tokens p:${metadata.tokens.prompt ?? ''} c:${metadata.tokens.completion ?? ''}`);
    const metaEl = el('div', 'text-[10px] mt-1 text-neutral-400', m.join(' • '));
    metaEl.setAttribute('dir', 'ltr');
    bubble.appendChild(metaEl);
  }
  wrap.appendChild(bubble);
  wrap._contentEl = contentEl; wrap._raw = content ?? '';
  return wrap;
}

export function chatItem(chat, { onSelect, onRename, onDelete, active }) {
  const row = el('div', 'group flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-neutral-800');
  if (active) row.classList.add('bg-neutral-800');
  const btn = el('button', 'flex-1 text-left px-1 py-1'); btn.textContent = chat.title || 'Untitled'; btn.onclick = () => onSelect(chat.id);
  const rename = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); rename.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>'; rename.title = 'Rename chat'; rename.onclick = (e) => { e.stopPropagation(); onRename(chat); };
  const del = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); del.innerHTML = '<i class="fa-solid fa-trash"></i>'; del.title = 'Delete chat'; del.onclick = (e) => { e.stopPropagation(); onDelete(chat); };
  row.append(btn, rename, del);
  return row;
}
