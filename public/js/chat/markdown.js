import { escapeHtml } from './util.js';

export function renderSafeMarkdown(text) {
  try {
    if (window.marked) {
      const renderer = new marked.Renderer();
      renderer.code = (code, infostring) => {
        const lang = (infostring || '').trim().toLowerCase();
        if (lang === 'mermaid') return `<div class="mermaid">${escapeHtml(code)}</div>`;
        return `<pre><code>${escapeHtml(code)}</code></pre>`;
      };
      const html = marked.parse(text ?? '', { gfm: true, breaks: true, renderer });
      return DOMPurify.sanitize(html, {
        ADD_TAGS: ['table','thead','tbody','tr','td','th','pre','code','span','div'],
        ADD_ATTR: ['class','href','title','target','rel','colspan','rowspan','align']
      });
    }
    const converted = convertMarkdownTables(text ?? '');
    return DOMPurify.sanitize(converted, { ADD_TAGS: ['table','thead','tbody','tr','td','th'] });
  } catch {
    return escapeHtml(text);
  }
}

export function styleRichContent(container) {
  container.querySelectorAll('table').forEach(t => {
    t.classList.add('w-full','text-sm','border-collapse','my-2');
    t.querySelectorAll('th,td').forEach(cell => cell.classList.add('border','border-slate-300','dark:border-neutral-700','px-2','py-1','align-top'));
    t.querySelectorAll('thead').forEach(th => th.classList.add('bg-slate-100','dark:bg-neutral-900'));
  });
  container.querySelectorAll('pre').forEach(pre => pre.classList.add('bg-slate-100','dark:bg-neutral-900','border','border-slate-300','dark:border-neutral-700','rounded-lg','p-3','overflow-x-auto','my-2'));
  container.querySelectorAll('code').forEach(code => code.classList.add('bg-slate-100','dark:bg-neutral-900','rounded','px-1','py-0.5'));
  container.querySelectorAll('blockquote').forEach(bq => bq.classList.add('border-l-4','border-slate-300','dark:border-neutral-700','pl-3','text-slate-700','dark:text-neutral-300','my-2'));
  container.querySelectorAll('ul').forEach(ul => ul.classList.add('list-disc','pl-5','my-2'));
  container.querySelectorAll('ol').forEach(ol => ol.classList.add('list-decimal','pl-5','my-2'));
  container.querySelectorAll('a').forEach(a => { a.classList.add('text-blue-600','dark:text-blue-400','underline','hover:opacity-90'); a.target = '_blank'; a.rel = 'noopener'; });
  if (window.mermaid && container.querySelector('.mermaid')){
    try { mermaid.init(undefined, container.querySelectorAll('.mermaid')); } catch {}
  }
}

function convertMarkdownTables(md){
  const lines = (md ?? '').split(/\r?\n/);
  const out = [];
  for (let i=0;i<lines.length;i++){
    const h = lines[i];
    const sep = lines[i+1] ?? '';
    if (/^\s*\|.*\|\s*$/.test(h) && /^\s*\|?\s*[:\-\s|]+\s*$/.test(sep)){
      const headers = h.trim().replace(/^\||\|$/g,'').split('|').map(s=>s.trim());
      i += 1;
      const rows = [];
      while (i+1 < lines.length && /^\s*\|.*\|\s*$/.test(lines[i+1])){
        const row = lines[i+1].trim().replace(/^\||\|$/g,'').split('|').map(s=>s.trim());
        rows.push(row); i += 1;
      }
      let html = '<table><thead><tr>' + headers.map(hc=>`<th>${escapeHtml(hc)}</th>`).join('') + '</tr></thead><tbody>';
      for (const r of rows){ html += '<tr>' + r.map(c=>`<td>${escapeHtml(c)}</td>`).join('') + '</tr>'; }
      html += '</tbody></table>';
      out.push(html);
    } else out.push(escapeHtml(h));
  }
  return out.join('\n');
}

