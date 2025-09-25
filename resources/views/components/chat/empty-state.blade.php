@props(['showAuthCta' => false])

<div class="mx-auto max-w-3xl mt-8 space-y-4 uchat-empty-state">
    <div class="flex items-center justify-center">
        <img src="/logo.svg" alt="UChat" class="h-12 md:h-16 object-contain" />
    </div>

    <div class="rounded-2xl backdrop-blur px-6 py-8 shadow-md glass-panel">
        <div class="text-center">
            <div class="mx-auto w-10 h-10 rounded-full flex items-center justify-center mb-3" style="background: color-mix(in srgb, var(--accent) 15%, transparent); color: var(--accent)">
                <i class="fa-solid fa-robot"></i>
            </div>
            <h2 class="text-2xl font-semibold">How can I help you today?</h2>
            <p class="text-sm" style="color: color-mix(in srgb, var(--text) 70%, transparent)">Start by asking a question or searching the archive.</p>
            @if($showAuthCta)
                <div class="mt-4 flex items-center justify-center gap-2">
                    <a href="/login" class="rounded-md px-4 py-2 text-sm text-white" style="background: var(--accent)">Sign in</a>
                    <a href="/register" class="rounded-md px-4 py-2 text-sm" style="border: 1px solid var(--border-muted); background: var(--surface); color: var(--text)">Create account</a>
                </div>
            @endif
        </div>
    </div>

    <div class="relative w-full overflow-hidden rounded-lg border divide-y" style="border-color: var(--border-muted); background: var(--surface); --tw-divide-opacity: 1;">
        <div class="uchat-ticker-row" dir="ltr">
            <div class="px-3 py-2 text-xs md:text-sm text-slate-700 dark:text-neutral-200">
                <div class="uchat-ticker-track uchat-anim-ltr">
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                </div>
            </div>
        </div>
        <div class="uchat-ticker-row" dir="rtl">
            <div class="px-3 py-2 text-xs md:text-sm text-slate-700 dark:text-neutral-200">
                <div class="uchat-ticker-track uchat-anim-rtl">
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span><span class="uchat-ticker-gap" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </div>
</div>
