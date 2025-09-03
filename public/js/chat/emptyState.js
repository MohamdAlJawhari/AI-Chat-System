import { el } from './dom.js';

export function renderEmptyState(showAuthCta = false) {
  // Inject ticker CSS once (seamless infinite loop via duplicated segments)
  if (!document.getElementById('ticker-style')) {
    const style = document.createElement('style');
    style.id = 'ticker-style';
    style.textContent = `
      .uchat-ticker-row { position: relative; overflow: hidden; }
      .uchat-ticker-track { display: flex; width: max-content; white-space: nowrap; will-change: transform; }
      .uchat-ticker-seg { padding-inline-end: 2rem; }
      @keyframes uchat-scroll-ltr { 0%{transform:translateX(0)} 100%{transform:translateX(-15%)} }
      @keyframes uchat-scroll-rtl { 0%{transform:translateX(0)} 100%{transform:translateX(15%)} }
      .uchat-anim-ltr { animation: uchat-scroll-ltr 30s linear infinite; }
      .uchat-anim-rtl { animation: uchat-scroll-rtl 30s linear infinite; }
    `;
    document.head.appendChild(style);
  }

  const wrap = el('div', 'mx-auto max-w-3xl mt-8 space-y-4');

  // Top logo
  const logo = document.createElement('div');
  logo.className = 'flex items-center justify-center';
  logo.innerHTML = `<img src="/logo.svg" alt="UChat" class="h-12 md:h-16 object-contain" />`;
  wrap.appendChild(logo);

  // Main card
  const card = el('div', 'rounded-2xl bg-white/80 dark:bg-neutral-800/80 backdrop-blur border border-slate-200 dark:border-neutral-700 px-6 py-8 shadow-md');
  const authCtas = showAuthCta ? `<div class=\"mt-4 flex items-center justify-center gap-2\">
    <button id=\"ctaSignIn\" class=\"rounded-md bg-slate-900 text-white dark:bg-white dark:text-black px-4 py-2 text-sm\">Sign in</button>
    <button id=\"ctaSignUp\" class=\"rounded-md bg-slate-200 text-slate-900 dark:bg-neutral-800 dark:text-white px-4 py-2 text-sm\">Create account</button>
  </div>` : '';
  card.innerHTML = `
    <div class="text-center">
      <div class="mx-auto w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-500 mb-3">
        <i class="fa-solid fa-robot"></i>
      </div>
      <h2 class="text-2xl font-semibold">How can I help you today?</h2>
      <p class="text-sm text-slate-600 dark:text-neutral-400">Start by asking a question or searching the archive.</p>
      ${authCtas}
    </div>`;
  wrap.appendChild(card);

  // Two-line ticker (EN + AR)
  const tickerBox = document.createElement('div');
  tickerBox.className = 'relative w-full overflow-hidden rounded-lg border border-slate-200 dark:border-neutral-700 bg-white/70 dark:bg-neutral-900/70 divide-y divide-slate-200 dark:divide-neutral-700';

  const buildTicker = ({ dir, text, animClass }) => {
    const row = document.createElement('div');
    row.className = 'uchat-ticker-row';
    row.setAttribute('dir', dir);
    const seg = `<span class=\"uchat-ticker-seg\">${text}</span>`;
    const segments = [seg, seg, seg, seg].join('');
    row.innerHTML = `<div class=\"px-3 py-2 text-xs md:text-sm text-slate-700 dark:text-neutral-200\"><div class=\"uchat-ticker-track ${animClass}\">${segments}</div></div>`;
    return row;
  };

  const enText = `||| => Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | `;
  const arText = ` | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر <= ||| `;

  tickerBox.appendChild(buildTicker({ dir: 'ltr', text: enText, animClass: 'uchat-anim-ltr' }));
  tickerBox.appendChild(buildTicker({ dir: 'rtl', text: arText, animClass: 'uchat-anim-rtl' }));

  wrap.appendChild(tickerBox);

  // Hook CTAs to open auth modal via a custom event
  if (showAuthCta) {
    const signin = card.querySelector('#ctaSignIn');
    const signup = card.querySelector('#ctaSignUp');
    if (signin) signin.addEventListener('click', ()=> window.dispatchEvent(new CustomEvent('auth:prompt',{ detail:{ mode:'login' } })));
    if (signup) signup.addEventListener('click', ()=> window.dispatchEvent(new CustomEvent('auth:prompt',{ detail:{ mode:'signup' } })));
  }

  return wrap;
}
