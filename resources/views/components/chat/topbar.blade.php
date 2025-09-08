<header class="relative z-40 p-3 border-b border-slate-200/60 dark:border-white/10 bg-white/50 dark:bg-black/20 backdrop-blur flex items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <button id="sidebarToggle" class="rounded-md px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-gray-100" title="Toggle sidebar">
            <i id="sidebarIcon" class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <div class="flex items-center gap-2">
        <label class="text-sm text-neutral-600 dark:text-neutral-400">Model</label>
        <select id="modelSelect" class="bg-white/70 dark:bg-black/30 border border-slate-300/50 dark:border-white/10 rounded-md px-3 py-1 text-sm outline-none focus:ring-1 focus:ring-[var(--accent)]"></select>
        <button id="themeToggle" class="rounded-md px-2 py-1 bg-white/70 dark:bg-black/30 border border-slate-300/50 dark:border-white/10 text-slate-800 dark:text-gray-100 hover:brightness-110" title="Toggle theme">
            <i id="themeIcon" class="fa-solid fa-moon"></i>
        </button>
        <div class="relative">
            <button id="userBtn" class="rounded-md px-2 py-1 bg-white/70 dark:bg-black/30 border border-slate-300/50 dark:border-white/10 text-slate-800 dark:text-gray-100 hover:brightness-110" title="Account">
                <i class="fa-regular fa-user"></i>
            </button>
            <div id="userMenu" class="hidden absolute right-0 mt-2 w-56 rounded-md border border-slate-200/60 dark:border-white/10 bg-white/90 dark:bg-black/60 backdrop-blur shadow-lg py-1 text-sm z-50"></div>
        </div>
    </div>
</header>
