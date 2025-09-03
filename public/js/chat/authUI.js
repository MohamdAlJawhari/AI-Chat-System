import { attachAuthRequester, login, register, logout, getToken } from './auth.js';

export function initAuthUI(updateAuthStatus){
  const userBtn = document.getElementById('userBtn');
  const userMenu = document.getElementById('userMenu');
  const modal = document.getElementById('authModal');
  const loginTab = document.getElementById('authLoginTab');
  const signupTab = document.getElementById('authSignupTab');
  const nameRow = document.getElementById('authNameRow');
  const inName = document.getElementById('authName');
  const inEmail = document.getElementById('authEmail');
  const inPassword = document.getElementById('authPassword');
  const errorEl = document.getElementById('authError');
  const submitBtn = document.getElementById('authSubmitBtn');
  const closeBtn = document.getElementById('authCloseBtn');

  let mode = 'login';
  function setMode(m){
    mode = m; errorEl.classList.add('hidden'); errorEl.textContent='';
    if (m==='login'){ loginTab.classList.add('bg-slate-200','dark:bg-neutral-800'); signupTab.classList.remove('bg-slate-200','dark:bg-neutral-800'); nameRow.classList.add('hidden'); }
    else { signupTab.classList.add('bg-slate-200','dark:bg-neutral-800'); loginTab.classList.remove('bg-slate-200','dark:bg-neutral-800'); nameRow.classList.remove('hidden'); }
  }
  setMode('login');
  loginTab.addEventListener('click', ()=> setMode('login'));
  signupTab.addEventListener('click', ()=> setMode('signup'));

  function openMenu(){ userMenu.classList.remove('hidden'); document.addEventListener('click', outsideClose, { once: true }); }
  function closeMenu(){ userMenu.classList.add('hidden'); }
  function outsideClose(e){ if (!userMenu.contains(e.target) && e.target !== userBtn) closeMenu(); else document.addEventListener('click', outsideClose, { once: true }); }
  userBtn.addEventListener('click', (e)=>{ e.stopPropagation(); if (userMenu.classList.contains('hidden')) openMenu(); else closeMenu(); });

  async function populateMenu(){
    const t = getToken();
    userMenu.innerHTML = '';
    if (!t){
      const si = document.createElement('button'); si.className='w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-neutral-800'; si.textContent='Sign in'; si.onclick=()=>{ closeMenu(); showModal('login'); };
      const su = document.createElement('button'); su.className='w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-neutral-800'; su.textContent='Sign up'; su.onclick=()=>{ closeMenu(); showModal('signup'); };
      userMenu.append(si, su);
    } else {
      // lightweight /auth/me for display
      try{
        const r = await fetch('/api/auth/me', { headers:{ 'Accept':'application/json','Authorization':`Bearer ${t}` }});
        const u = r.ok ? await r.json() : null;
        const info = document.createElement('div'); info.className='px-3 py-2 text-slate-600 dark:text-neutral-300'; info.textContent = u ? `${u.email} (${u.role})` : 'Signed in';
        const so = document.createElement('button'); so.className='w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-neutral-800'; so.textContent='Sign out'; so.onclick=async()=>{ closeMenu(); await logout(); await updateAuthStatus(); };
        const sw = document.createElement('button'); sw.className='w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-neutral-800'; sw.textContent='Switch account'; sw.onclick=()=>{ closeMenu(); showModal('login'); };
        userMenu.append(info, so, sw);
      }catch{
        const so = document.createElement('button'); so.className='w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-neutral-800'; so.textContent='Sign out'; so.onclick=async()=>{ closeMenu(); await logout(); await updateAuthStatus(); };
        userMenu.append(so);
      }
    }
  }

  function showModal(m='login'){
    setMode(m); modal.classList.remove('hidden');
  }
  function hideModal(){ modal.classList.add('hidden'); }
  closeBtn.addEventListener('click', hideModal);

  submitBtn.addEventListener('click', async ()=>{
    errorEl.classList.add('hidden'); errorEl.textContent='';
    const email = inEmail.value.trim(); const password = inPassword.value.trim(); const name = inName.value.trim();
    try{
      if (mode==='login') await login(email, password);
      else await register(name, email, password);
      hideModal(); await updateAuthStatus(); await populateMenu();
      window.dispatchEvent(new Event('auth:changed'));
    }catch(e){ errorEl.textContent = String(e?.message || 'Auth failed'); errorEl.classList.remove('hidden'); }
  });

  attachAuthRequester(() => new Promise((resolve)=>{
    showModal('login');
    const handler = ()=>{ hideModal(); window.removeEventListener('auth:changed', handler); resolve(true); };
    window.addEventListener('auth:changed', handler);
  }));

  // Allow external components to open the modal with a preferred mode
  window.addEventListener('auth:prompt', (e)=>{
    const mode = e?.detail?.mode === 'signup' ? 'signup' : 'login';
    showModal(mode);
  });

  // initial populate
  populateMenu();
  window.addEventListener('auth:changed', populateMenu);

  return { showModal, populateMenu };
}
