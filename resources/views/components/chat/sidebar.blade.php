<aside id="sidebar" style="width: var(--sidebar-w, 16rem); background: var(--surface); border-right: 1px solid var(--border-muted)" class="shrink-0 backdrop-blur p-4 flex flex-col transition-[width] duration-200 ease-in-out overflow-hidden">
    <div class="text-xl font-semibold mb-3 flex items-center gap-2">
        <img src="/logo_login.svg" alt="Logo" class="h-7 w-7 object-contain" />
        UChat
    </div>
    <button id="newChatBtn" class="mb-3 rounded-xl bg-[var(--accent)] hover:brightness-110 text-white px-3 py-2 text-sm flex items-center justify-between shadow-sm">
        <span>New chat</span>
        <i class="fa-solid fa-plus"></i>
    </button>
    <div class="text-xs uppercase tracking-wide mb-2" style="color: color-mix(in srgb, var(--text) 60%, transparent)">Chats</div>
    <div id="chatList" class="space-y-1 overflow-y-auto grow">
    </div>
    <div class="pt-4 text-xs" style="color: color-mix(in srgb, var(--text) 60%, transparent)">
        <div id="authStatus" class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full bg-red-500" id="authDot"></span>
            <span id="authText">Not signed in</span>
        </div>
        <div class="mt-2 opacity-70">© UChat (dev)</div>
    </div>
</aside>
<div id="sidebarDivider" class="w-1 cursor-col-resize" style="background: var(--surface)"></div>
