@props(['showAuthCta' => false])

<div class="mx-auto max-w-3xl mt-8 space-y-4">
    <!-- Top logo -->
    <div class="flex items-center justify-center">
        <img src="/logo.svg" alt="UChat" class="h-12 md:h-16 object-contain" />
    </div>

    <!-- Main card -->
    <div class="rounded-2xl bg-white/80 dark:bg-neutral-800/80 backdrop-blur border border-slate-200 dark:border-neutral-700 px-6 py-8 shadow-md">
        <div class="text-center">
            <div class="mx-auto w-10 h-10 rounded-full flex items-center justify-center bg-[var(--accent)]/15 text-[var(--accent)] mb-3">
                <i class="fa-solid fa-robot"></i>
            </div>
            <h2 class="text-2xl font-semibold">How can I help you today?</h2>
            <p class="text-sm text-slate-600 dark:text-neutral-400">Start by asking a question or searching the archive.</p>
            @if($showAuthCta)
            <div class="mt-4 flex items-center justify-center gap-2">
                <a href="/login" class="rounded-md bg-slate-900 text-white dark:bg-white dark:text-black px-4 py-2 text-sm">Sign in</a>
                <a href="/register" class="rounded-md bg-slate-200 text-slate-900 dark:bg-neutral-800 dark:text-white px-4 py-2 text-sm">Create account</a>
            </div>
            @endif
        </div>
    </div>

    <!-- Two-line ticker (EN + AR) -->
    <div class="relative w-full overflow-hidden rounded-lg border border-slate-200 dark:border-neutral-700 bg-white/70 dark:bg-neutral-900/70 divide-y divide-slate-200 dark:divide-neutral-700">
        <div class="uchat-ticker-row" dir="ltr">
            <div class="px-3 py-2 text-xs md:text-sm text-slate-700 dark:text-neutral-200">
                <div class="uchat-ticker-track uchat-anim-ltr">
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span>
                    <span class="uchat-ticker-seg">||| =&gt; Unews is a multimedia news agency producing news videos and broadcasting live events | We are a leading news agency that provides reliable news, envisioning accurate and objective coverage | We cover events in real time via live broadcasting techniques, providing full news content on an online platform: video, audio files, photographs and texts | Unews provides its services in five languages for TV channels, radios, press and online media | We deliver news services upon request. We provide guests for programs and assist correspondents to go live from the scene | </span>
                </div>
            </div>
        </div>
        <div class="uchat-ticker-row" dir="rtl">
            <div class="px-3 py-2 text-xs md:text-sm text-slate-700 dark:text-neutral-200">
                <div class="uchat-ticker-track uchat-anim-rtl">
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span>
                    <span class="uchat-ticker-seg"> | نحن وكالة أنباء رائدة نقدم الأخبار الموثوقة الصحيحة والدقة والموضوعية | نحن ننقل الأحداث في وقتها الحقيقي عبر تقنيات البث المباشر ونوفر المحتوى الإخباري الكامل: الفيديو والصوت والصور والنصوص. كل ذلك على منصة إلكترونية واحدة | تقدم يو إن نيوز خدماتها الإعلامية إلى القنوات التلفزيونية والإذاعات والصحف والإعلام الإلكتروني بخمس لغات عالمية | دائماً حساس بقوة الخبر &lt;= ||| </span>
                </div>
            </div>
        </div>
    </div>
</div>

