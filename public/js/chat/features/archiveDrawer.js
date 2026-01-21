/**
 * Archive drawer behavior: resize via divider, collapse/expand and persistence.
 * Stores width/hidden state in localStorage.
 */
import { elements } from '../core/dom.js';

export function initArchiveDrawer(state) {
  const drawer = elements.archiveDrawer;
  const divider = elements.archiveDrawerDivider;
  const toggle = elements.archiveDrawerToggle;
  const icon = elements.archiveDrawerIcon;

  const padDefault = drawer ? window.getComputedStyle(drawer).padding : '16px';
  const borderLDefault = drawer ? window.getComputedStyle(drawer).borderLeftWidth : '1px';

  function setDrawerWidth(px){
    const min = 240, max = 520;
    const clamped = Math.max(min, Math.min(max, px));
    document.documentElement.style.setProperty('--archive-w', clamped + 'px');
    localStorage.setItem('archiveDrawerW', String(clamped));
  }

  function hideDrawer(hidden){
    state.archiveDrawerHidden = hidden;
    if (hidden){
      document.documentElement.style.setProperty('--archive-w', '0px');
      if (drawer) { drawer.style.pointerEvents = 'none'; drawer.style.padding = '0px'; drawer.style.borderLeftWidth = '0px'; }
      if (divider) divider.style.display = 'none';
    } else {
      const w = parseInt(localStorage.getItem('archiveDrawerW') || '352', 10) || 352;
      document.documentElement.style.setProperty('--archive-w', w + 'px');
      if (drawer) { drawer.style.pointerEvents = ''; drawer.style.padding = padDefault; drawer.style.borderLeftWidth = borderLDefault; }
      if (divider) divider.style.display = '';
    }
    localStorage.setItem('archiveDrawerHidden', hidden ? '1' : '0');
    if (icon) icon.className = hidden ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right';
  }

  function restore(){
    const w = parseInt(localStorage.getItem('archiveDrawerW') || '0', 10);
    if (w) document.documentElement.style.setProperty('--archive-w', w + 'px');
    const hidden = localStorage.getItem('archiveDrawerHidden') === '1';
    hideDrawer(hidden);
  }

  let dragging = false;
  if (divider){
    divider.addEventListener('mousedown', ()=>{ dragging = true; document.body.classList.add('select-none'); });
    window.addEventListener('mouseup', ()=>{ dragging = false; document.body.classList.remove('select-none'); });
    window.addEventListener('mousemove', (e)=>{
      if (dragging && !state.archiveDrawerHidden) {
        const width = Math.max(0, window.innerWidth - e.clientX);
        setDrawerWidth(width);
      }
    });
  }
  if (toggle){
    toggle.addEventListener('click', ()=> hideDrawer(!state.archiveDrawerHidden));
  }

  restore();

  return { hideDrawer, setDrawerWidth };
}
