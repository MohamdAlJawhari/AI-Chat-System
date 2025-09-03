import { elements } from './dom.js';
import { state } from './state.js';
import { initSidebar } from './sidebar.js';
import { apiGet, apiPost, apiPatch, apiDelete } from './api.js';
import { messageBubble, chatItem } from './ui.js';
import { renderEmptyState } from './emptyState.js';
import { getToken } from './auth.js';
import { initAuthUI } from './authUI.js';
import { initComposer } from './composer.js';
import { sendMessage } from './stream.js';

function setTheme(mode){
  if (mode === 'dark') document.documentElement.classList.add('dark');
  else document.documentElement.classList.remove('dark');
  localStorage.setItem('theme', mode);
  const icon = document.getElementById('themeIcon') || (elements.themeToggle ? elements.themeToggle.querySelector('i') : null);
  if (icon) icon.className = mode === 'dark' ? 'fa-solid fa-moon' : 'fa-notdog fa-solid fa-sun';
  if (window.mermaid){ try{ mermaid.initialize({ startOnLoad:false, theme: mode==='dark'?'dark':'default' }); }catch{} }
}
function initTheme(){ const t = localStorage.getItem('theme'); const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; setTheme(t || (prefersDark ? 'dark' : 'light')); }

async function loadMessages(){
  const { messagesEl } = elements; if (!state.currentChatId){ messagesEl.innerHTML=''; return; }
  const msgs = await apiGet(`/api/chats/${state.currentChatId}/messages`);
  messagesEl.innerHTML=''; if (msgs.length===0) messagesEl.appendChild(renderEmptyState());
  msgs.forEach(m=> messagesEl.appendChild(messageBubble(m.role, m.content, m.metadata)));
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

async function loadChats(){
  const chats = await apiGet('/api/chats'); state.chatsCache = chats;
  const { chatListEl, modelSelect } = elements; chatListEl.innerHTML='';
  chats.forEach(c => chatListEl.appendChild(chatItem(c, {
    onSelect: async (id)=>{ state.currentChatId = id; await loadMessages(); await loadChats(); },
    onRename: async (chat)=>{ const newTitle = prompt('Rename chat', chat.title||''); if (newTitle===null) return; await apiPatch(`/api/chats/${chat.id}`, { title:newTitle }); await loadChats(); },
    onDelete: async (chat)=>{ if (!confirm('Delete this chat?')) return; await apiDelete(`/api/chats/${chat.id}`); if (state.currentChatId===chat.id){ state.currentChatId=null; elements.messagesEl.innerHTML=''; } await loadChats(); },
    active: c.id === state.currentChatId
  })));
  if (!state.currentChatId && chats[0]){
    state.currentChatId = chats[0].id; const cur = chats[0];
    if (cur?.settings?.model && [...modelSelect.options].some(o=>o.value===cur.settings.model)) modelSelect.value = cur.settings.model;
    await loadMessages();
  }
  return chats;
}

async function createChatIfNeeded(){ if (state.currentChatId) return state.currentChatId; const chat = await apiPost('/api/chats',{ settings:{ model: elements.modelSelect.value }}); state.currentChatId = chat.id; await loadChats(); return state.currentChatId; }

async function bootstrap(){
  initTheme(); initSidebar(state); initComposer();
  // Auth status in sidebar
  async function updateAuthStatus(){
    const dot = document.getElementById('authDot');
    const text = document.getElementById('authText');
    try {
      const t = getToken();
      if (!t) throw new Error('no token');
      const r = await fetch('/api/auth/me', { headers: { 'Accept':'application/json', 'Authorization': `Bearer ${t}` } });
      if (!r.ok) throw new Error('unauth');
      const u = await r.json();
      if (dot) dot.className = 'inline-flex h-2 w-2 rounded-full bg-emerald-500';
      if (text) text.textContent = `Signed in as ${u.email} (${u.role})`;
    } catch {
      if (dot) dot.className = 'inline-flex h-2 w-2 rounded-full bg-red-500';
      if (text) text.textContent = 'Not signed in';
    }
  }
  // models
  try{
    const models = await apiGet('/api/models');
    if (Array.isArray(models) && models.length){ elements.modelSelect.innerHTML=''; for (const m of models){ const opt = document.createElement('option'); opt.value=m; opt.textContent=m; elements.modelSelect.appendChild(opt);} }
  }catch{ elements.modelSelect.innerHTML=''; ['gpt-oss:20b','openchat'].forEach(m=>{ const opt = document.createElement('option'); opt.value=m; opt.textContent=m; elements.modelSelect.appendChild(opt); }); }
  // If not signed in, show friendly empty state and let user sign in/up
  if (!getToken()) {
    elements.messagesEl.innerHTML = '';
    elements.messagesEl.appendChild(renderEmptyState(true));
    await updateAuthStatus();
    initAuthUI(updateAuthStatus);
    // When auth changes, load chats
    window.addEventListener('auth:changed', async ()=>{ await loadChats(); await updateAuthStatus(); });
  } else {
    await loadChats();
    await updateAuthStatus();
    initAuthUI(updateAuthStatus);
  }

  // events
  elements.newChatBtn.addEventListener('click', async ()=>{ state.currentChatId=null; await createChatIfNeeded(); elements.messagesEl.innerHTML=''; await updateAuthStatus(); });
  elements.sendBtn.addEventListener('click', ()=> sendMessage(state,{ createChatIfNeeded, loadMessages, loadChats }));
  elements.composer.addEventListener('keydown', (e)=>{ if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMessage(state,{ createChatIfNeeded, loadMessages, loadChats }); }});
  elements.modelSelect.addEventListener('change', async ()=>{ if (state.currentChatId){ try{ await apiPatch(`/api/chats/${state.currentChatId}`, { settings:{ model: elements.modelSelect.value } }); await loadChats(); }catch(e){ console.error('Failed to update chat model', e); } }});
  if (elements.themeToggle){ elements.themeToggle.addEventListener('click', ()=>{ const isDark = document.documentElement.classList.contains('dark'); setTheme(isDark ? 'light' : 'dark'); }); }
  window.addEventListener('keydown', async (e)=>{ const k = e.key?e.key.toLowerCase():''; if ((e.ctrlKey||e.metaKey) && k==='n'){ e.preventDefault(); state.currentChatId=null; await createChatIfNeeded(); await loadMessages(); await loadChats(); }});
}

bootstrap();
