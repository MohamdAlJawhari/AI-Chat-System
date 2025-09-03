<aside id="sidebar" style="width: var(--sidebar-w, 16rem)" class="shrink-0 bg-slate-50 dark:bg-panel border-r border-slate-200 dark:border-neutral-800 p-4 flex flex-col transition-[width] duration-200 ease-in-out overflow-hidden">
    <div class="text-xl font-semibold mb-3 flex items-center gap-2">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded bg-red-600 text-white font-bold">U</span>
        UChat
    </div>
    <button id="newChatBtn" class="mb-3 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-2 text-sm flex items-center justify-between">
        <span>New chat</span>
        <i class="fa-solid fa-plus"></i>
    </button>
    <div class="text-xs text-neutral-400 uppercase tracking-wide mb-2">Chats</div>
    <div id="chatList" class="space-y-1 overflow-y-auto grow"></div>
    <div class="pt-4 text-xs text-neutral-500">
        <div id="authStatus" class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-red-500" id="authDot"></span>
            <span id="authText">Not signed in</span>
        </div>
        <div class="mt-2">© UChat (dev)</div>
    </div>
</aside>
<div id="sidebarDivider" class="w-1 cursor-col-resize bg-slate-200 hover:bg-slate-300 dark:bg-neutral-800 dark:hover:bg-neutral-700"></div>
