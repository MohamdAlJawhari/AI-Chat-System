<x-layouts.app :title="'UChat'">
    <div class="relative flex h-full">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.12),transparent_60%)]"></div>
        <x-chat.sidebar />
        <main class="flex-1 flex flex-col">
            <x-chat.topbar />
            <x-chat.messages />
            <x-chat.composer />
        </main>
    </div>

    <x-slot name="scripts">
        <script type="module" src="/js/chat/main.js"></script>
    </x-slot>
    <x-auth.modal />
</x-layouts.app>
