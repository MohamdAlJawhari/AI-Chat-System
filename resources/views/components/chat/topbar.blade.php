<header class="relative p-3 border-b border-slate-200 dark:border-neutral-800 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <button id="sidebarToggle" class="rounded-md px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-gray-100" title="Toggle sidebar">
            <i id="sidebarIcon" class="fa-solid fa-chevron-left"></i>
        </button>
    </div>

    <div class="flex items-center gap-2">
        <label class="text-sm text-neutral-600 dark:text-neutral-400">Model</label>
        <select id="modelSelect" class="bg-white border border-slate-300 dark:bg-neutral-900 dark:border-neutral-700 rounded-md px-3 py-1 text-sm"></select>
        <button id="themeToggle" class="rounded-md px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-gray-100" title="Toggle theme">
            <i id="themeIcon" class="fa-solid fa-moon"></i>
        </button>
        <div class="relative">
            <button id="userBtn" class="rounded-md px-2 py-1 bg-slate-200 hover:bg-slate-300 text-slate-800 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-gray-100" title="Account">
                <i class="fa-regular fa-user"></i>
            </button>
            <div id="userMenu" class="hidden absolute right-0 mt-2 w-56 rounded-md border border-slate-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 shadow-lg py-1 text-sm z-30"></div>
        </div>
    </div>
</header>
