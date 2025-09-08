import { elements } from './core/dom.js';
import { state } from './core/state.js';
import { initSidebar } from './features/sidebar.js';
import { apiGet, apiPost, apiPatch, apiDelete } from './api/api.js';
import { messageBubble, chatItem } from './ui/ui.js';
import { renderEmptyState } from './ui/emptyState.js';
import { initComposer } from './features/composer.js';
import { sendMessage } from './features/stream.js';

function setTheme(mode){
  if (mode === 'dark') document.documentElement.classList.add('dark');
  else document.documentElement.classList.remove('dark');
  localStorage.setItem('theme', mode);
  const icon = document.getElementById('themeIcon') || (elements.themeToggle ? elements.themeToggle.querySelector('i') : null);
  if (icon) icon.className = mode === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
  if (window.mermaid){ try{ mermaid.initialize({ startOnLoad:false, theme: mode==='dark'?'dark':'default' }); }catch{} }
}
function initTheme(){ const t = localStorage.getItem('theme'); const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; setTheme(t || (prefersDark ? 'dark' : 'light')); }

function initUserMenu(){
  const btn = document.getElementById('userBtn');
  const menu = document.getElementById('userMenu');
  if (!btn || !menu) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const authStatus = document.querySelector('meta[name="auth-status"]')?.getAttribute('content') || 'guest';
  // Populate menu once
  menu.innerHTML = '';
  if (authStatus === 'auth') {
    const profile = document.createElement('a'); profile.href='/profile'; profile.className='block px-3 py-2 hover:bg-slate-100 dark:hover:bg-neutral-800'; profile.textContent='Profile';
    const form = document.createElement('form'); form.method='POST'; form.action='/logout'; form.innerHTML=`<input type=\"hidden\" name=\"_token\" value=\"${csrf}\">`;
    const outBtn = document.createElement('button'); outBtn.type='submit'; outBtn.className='w-full text-left px-3 py-2 hover:bg-slate-100 dark:hover:bg-neutral-800'; outBtn.textContent='Sign out';
    form.appendChild(outBtn); menu.append(profile, form);
  } else {
    const li = document.createElement('a'); li.href='/login'; li.className='block px-3 py-2 hover:bg-slate-100 dark:hover:bg-neutral-800'; li.textContent='Sign in';
    const rg = document.createElement('a'); rg.href='/register'; rg.className='block px-3 py-2 hover:bg-slate-100 dark:hover:bg-neutral-800'; rg.textContent='Create account';
    menu.append(li, rg);
  }
  function open(){ menu.classList.remove('hidden'); document.addEventListener('click', outside, { once:true }); }
  function close(){ menu.classList.add('hidden'); }
  function outside(e){ if (!menu.contains(e.target) && e.target !== btn) close(); else document.addEventListener('click', outside, { once:true }); }
  btn.addEventListener('click', (e)=>{ e.stopPropagation(); if (menu.classList.contains('hidden')) open(); else close(); });
}

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
  initTheme(); initSidebar(state); initComposer(); initUserMenu();
  // Auth status (session-based)
  function updateAuthStatus(){
    const dot = document.getElementById('authDot');
    const text = document.getElementById('authText');
    const authStatus = document.querySelector('meta[name="auth-status"]')?.getAttribute('content') || 'guest';
    const isAuth = authStatus === 'auth';
    if (dot) dot.className = 'inline-flex h-2 w-2 rounded-full ' + (isAuth ? 'bg-emerald-500' : 'bg-red-500');
    if (text) text.textContent = isAuth ? 'Signed in' : 'Not signed in';
  }
  // models
  try{
    const models = await apiGet('/api/models');
    if (Array.isArray(models) && models.length){ elements.modelSelect.innerHTML=''; for (const m of models){ const opt = document.createElement('option'); opt.value=m; opt.textContent=m; elements.modelSelect.appendChild(opt);} }
  }catch{ elements.modelSelect.innerHTML=''; ['gpt-oss:20b','openchat'].forEach(m=>{ const opt = document.createElement('option'); opt.value=m; opt.textContent=m; elements.modelSelect.appendChild(opt); }); }
  await loadChats();
  updateAuthStatus();

  // events
  elements.newChatBtn.addEventListener('click', async ()=>{ state.currentChatId=null; await createChatIfNeeded(); elements.messagesEl.innerHTML=''; await updateAuthStatus(); });
  elements.sendBtn.addEventListener('click', ()=> sendMessage(state,{ createChatIfNeeded, loadMessages, loadChats }));
  elements.composer.addEventListener('keydown', (e)=>{ if (e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendMessage(state,{ createChatIfNeeded, loadMessages, loadChats }); }});
  elements.modelSelect.addEventListener('change', async ()=>{ if (state.currentChatId){ try{ await apiPatch(`/api/chats/${state.currentChatId}`, { settings:{ model: elements.modelSelect.value } }); await loadChats(); }catch(e){ console.error('Failed to update chat model', e); } }});
  if (elements.themeToggle){ elements.themeToggle.addEventListener('click', ()=>{ const isDark = document.documentElement.classList.contains('dark'); setTheme(isDark ? 'light' : 'dark'); }); }
  window.addEventListener('keydown', async (e)=>{ const k = e.key?e.key.toLowerCase():''; if ((e.ctrlKey||e.metaKey) && k==='n'){ e.preventDefault(); state.currentChatId=null; await createChatIfNeeded(); await loadMessages(); await loadChats(); }});
}

bootstrap();
