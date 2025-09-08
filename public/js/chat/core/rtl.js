/** Heuristics for detecting RTL content */
export function detectDirection(text) {
  const s = String(text || '');
  const rtlMatch = s.match(/[\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/g);
  const ltrMatch = s.match(/[A-Za-z]/g);
  const rtlCount = rtlMatch ? rtlMatch.length : 0;
  const ltrCount = ltrMatch ? ltrMatch.length : 0;
  if (rtlCount === 0 && ltrCount === 0) return 'ltr';
  return rtlCount >= ltrCount ? 'rtl' : 'ltr';
}

/** Apply dir and alignment based on detected direction */
export function applyDirection(el, text) {
  const dir = detectDirection(text);
  el.setAttribute('dir', dir);
  el.style.unicodeBidi = 'plaintext';
  el.classList.toggle('text-right', dir === 'rtl');
  el.classList.toggle('text-left', dir !== 'rtl');
}
