<x-layout.page :title="'Chat Conversation'">
    <div class="relative flex h-full">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.12),transparent_60%)]"></div>
        <x-chat.sidebar />
        <main class="flex-1 flex flex-col">
            <x-chat.topbar />
            <x-chat.message-list>
                <x-chat.message :role="'assistant'" content="Select a conversation from the sidebar." />
            </x-chat.message-list>
            <x-chat.composer />
        </main>
        <x-chat.archive-drawer />
    </div>
</x-layout.page>
