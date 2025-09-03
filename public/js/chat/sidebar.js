import { elements } from './dom.js';

export function initSidebar(state) {
  const sidebar = elements.sidebar;
  const divider = elements.divider;
  const sidebarToggle = elements.sidebarToggle;
  const sidebarIcon = elements.sidebarIcon;

  const padDefault = sidebar ? window.getComputedStyle(sidebar).padding : '16px';
  const borderRDefault = sidebar ? window.getComputedStyle(sidebar).borderRightWidth : '1px';

  function setSidebarWidth(px){
    const min = 160, max = 480;
    const clamped = Math.max(min, Math.min(max, px));
    document.documentElement.style.setProperty('--sidebar-w', clamped + 'px');
    localStorage.setItem('sidebarW', String(clamped));
  }

  function hideSidebar(hidden){
    state.sidebarHidden = hidden;
    if (hidden){
      document.documentElement.style.setProperty('--sidebar-w', '0px');
      if (sidebar) { sidebar.style.pointerEvents = 'none'; sidebar.style.padding = '0px'; sidebar.style.borderRightWidth = '0px'; }
      if (divider) divider.style.display = 'none';
    } else {
      const w = parseInt(localStorage.getItem('sidebarW') || '256', 10) || 256;
      document.documentElement.style.setProperty('--sidebar-w', w + 'px');
      if (sidebar) { sidebar.style.pointerEvents = ''; sidebar.style.padding = padDefault; sidebar.style.borderRightWidth = borderRDefault; }
      if (divider) divider.style.display = '';
    }
    localStorage.setItem('sidebarHidden', hidden ? '1' : '0');
    if (sidebarIcon) sidebarIcon.className = hidden ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
  }

  function restore(){
    const w = parseInt(localStorage.getItem('sidebarW') || '0', 10);
    if (w) document.documentElement.style.setProperty('--sidebar-w', w + 'px');
    const hidden = localStorage.getItem('sidebarHidden') === '1';
    hideSidebar(hidden);
  }

  let dragging = false;
  if (divider){
    divider.addEventListener('mousedown', ()=>{ dragging = true; document.body.classList.add('select-none'); });
    window.addEventListener('mouseup', ()=>{ dragging = false; document.body.classList.remove('select-none'); });
    window.addEventListener('mousemove', (e)=>{ if (dragging && !state.sidebarHidden) setSidebarWidth(e.clientX); });
  }
  if (sidebarToggle){
    sidebarToggle.addEventListener('click', ()=> hideSidebar(!state.sidebarHidden));
  }

  restore();

  return { hideSidebar, setSidebarWidth };
}

