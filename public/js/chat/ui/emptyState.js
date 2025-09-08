/**
 * Empty-state rendering using a Blade-provided template.
 * - See resources/views/chat.blade.php -> #empty-state-template
 * - If the template is missing, a minimal fallback is returned.
 */
export function renderEmptyState(){
  const tpl = document.getElementById('empty-state-template');
  if (tpl && 'content' in tpl){
    const first = tpl.content.firstElementChild;
    if (first) return first.cloneNode(true);
  }
  const div = document.createElement('div');
  div.className = 'mx-auto max-w-3xl mt-8 text-center text-sm text-neutral-400 uchat-empty-state';
  div.textContent = 'Start by asking a question…';
  return div;
}

// Optional: render just the ticker block if needed elsewhere.
export function renderTickerOnly(){
  const box = document.createElement('div');
  box.className = 'relative w-full overflow-hidden rounded-lg border border-slate-200 bg-white/70 divide-y divide-slate-200';
  const buildTicker = ({ dir, text, animClass }) => {
    const row = document.createElement('div');
    row.className = 'uchat-ticker-row';
    row.setAttribute('dir', dir);
    const seg = `<span class=\"uchat-ticker-seg\">${text}</span>`;
    const segments = [seg, seg, seg, seg].join('');
    row.innerHTML = `<div class=\"px-3 py-2 text-xs md:text-sm text-slate-700\"><div class=\"uchat-ticker-track ${animClass}\">${segments}</div></div>`;
    return row;
  };
  const enText = `||| => Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | `;
  const arText = ` | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر <= ||| `;
  box.appendChild(buildTicker({ dir:'ltr', text: enText, animClass:'uchat-anim-ltr' }));
  box.appendChild(buildTicker({ dir:'rtl', text: arText, animClass:'uchat-anim-rtl' }));
  return box;
}
