<x-layout.page :title="'UChat'">
    <div class="relative flex h-full">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.12),transparent_60%)]"></div>
        <x-chat.sidebar />
        <main class="flex-1 flex flex-col">
            <x-chat.topbar />
            <x-chat.message-list />
            <x-chat.composer />
        </main>
        <x-chat.archive-drawer />
    </div>

    <template id="empty-state-template">
        <x-chat.empty-state :show-auth-cta="false" />
    </template>

    <x-slot name="scripts">
        @php($__filter_opts_ver = @filemtime(public_path('js/filter-options.js')) ?: time())
        @php($__main_ver = @filemtime(public_path('js/chat/main.js')) ?: time())
        <script src="/js/filter-options.js?v={{ $__filter_opts_ver }}"></script>
        <script type="module" src="/js/chat/main.js?v={{ $__main_ver }}"></script>
    </x-slot>
</x-layout.page>
