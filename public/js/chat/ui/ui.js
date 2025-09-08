import { el } from '../core/dom.js';
import { applyDirection } from '../core/rtl.js';
import { renderSafeMarkdown, styleRichContent } from './markdown.js';

function downloadText(filename, text){
  try {
    const blob = new Blob([text ?? ''], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=> URL.revokeObjectURL(url), 1000);
  } catch (e) { console.error('Download failed', e); }
}

function timestampName(){
  const d = new Date();
  const pad = (n)=> String(n).padStart(2,'0');
  return `uchat-${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}.txt`;
}

function sanitizeTitle(s){
  const base = String(s || 'untitled').trim().slice(0, 80);
  const noInvalid = base.replace(/[^A-Za-z0-9\-_\s]/g, '_');
  return noInvalid.replace(/\s+/g, '_') || 'untitled';
}

function buildFilename(chatTitle){
  const d = new Date();
  const pad = (n)=> String(n).padStart(2,'0');
  const ts = `${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
  return `uchat-${sanitizeTitle(chatTitle)}-${ts}.txt`;
}

async function copyTextToClipboard(text){
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

export function messageBubble(role, content, metadata = null, opts = {}) {
  const wrap = el('div', 'group relative flex ' + (role === 'user' ? 'justify-end' : 'justify-start'));
  if (role !== 'user') wrap.classList.add('pb-6');
  const bubble = el('div', (
    role === 'user'
      ? 'relative max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-blue-600 text-white whitespace-pre-wrap'
      : 'relative max-w-[80%] text-sm px-0 py-0 bg-transparent'
  ));
  const contentEl = el('div', 'rich');
  if (role === 'user') contentEl.textContent = content ?? '';
  else { contentEl.innerHTML = renderSafeMarkdown(content ?? ''); styleRichContent(contentEl); }
  applyDirection(contentEl, content ?? '');
  bubble.appendChild(contentEl);

  // Overlays for assistant messages: toolbar bottom-left (latency + actions)
  if (role !== 'user'){
    const toolbar = el('div', 'absolute -bottom-5 left-0 flex items-center gap-2');
    const btnCls = 'h-6 w-6 rounded-full bg-slate-200 hover:bg-slate-300 text-slate-700 dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-white flex items-center justify-center';

    const copyBtn = el('button', btnCls);
    copyBtn.title = 'Copy';
    copyBtn.innerHTML = '<i class="fa-regular fa-copy text-[12px]"></i>';
    copyBtn.addEventListener('click', async (e)=>{
      e.stopPropagation();
      const ok = await copyTextToClipboard(wrap._raw ?? '');
      if (ok) {
        const prev = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fa-solid fa-check text-[12px]"></i>';
        setTimeout(()=>{ copyBtn.innerHTML = prev; }, 800);
      }
    });

    const dlBtn = el('button', btnCls);
    dlBtn.title = 'Download';
    dlBtn.innerHTML = '<i class="fa-solid fa-download text-[12px]"></i>';
    dlBtn.addEventListener('click', (e)=>{
      e.stopPropagation();
      const fname = buildFilename(opts.chatTitle);
      downloadText(fname, wrap._raw ?? '');
    });

    const latency = el('div','text-[10px] text-neutral-400/90 select-none hidden','');
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
  // expose bubble for stream logic (to style pending vs rendered)
  wrap._bubble = bubble;
  return wrap;
}

export function chatItem(chat, { onSelect, onRename, onDelete, active }) {
  const row = el('div', 'group flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-neutral-800');
  if (active) row.classList.add('bg-slate-200','dark:bg-neutral-800');
  const btn = el('button', 'flex-1 text-left px-1 py-1'); btn.textContent = chat.title || 'Untitled'; btn.onclick = () => onSelect(chat.id);
  const rename = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); rename.innerHTML = '<i class="fa-solid fa-pen-to-square"></i>'; rename.title = 'Rename chat'; rename.onclick = (e) => { e.stopPropagation(); onRename(chat); };
  const del = el('button', 'opacity-60 hover:opacity-100 text-xs px-1 py-0.5 hidden group-hover:block'); del.innerHTML = '<i class="fa-solid fa-trash"></i>'; del.title = 'Delete chat'; del.onclick = (e) => { e.stopPropagation(); onDelete(chat); };
  row.append(btn, rename, del);
  return row;
}
