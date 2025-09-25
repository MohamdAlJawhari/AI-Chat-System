<x-layout.page :title="'Chat Settings'">
    <div class="relative min-h-screen">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.08),transparent_60%)]"></div>

        <div class="relative mx-auto max-w-4xl px-4 py-12 space-y-8">
            <x-ui.card title="Preferences">
                <form class="space-y-6">
                    <x-form.group label="Default model" for="chat-default-model" hint="This model pre-selects when you start a new conversation.">
                        <x-form.select id="chat-default-model" name="default_model">
                            <option value="gpt-4">GPT-4</option>
                            <option value="gpt-4o">GPT-4o</option>
                            <option value="openchat">OpenChat</option>
                        </x-form.select>
                    </x-form.group>

                    <x-form.group label="Message history" for="chat-history-window" hint="Number of turns to retain when sending context to the assistant.">
                        <x-form.input id="chat-history-window" name="history_window" type="number" min="1" value="10" />
                    </x-form.group>

                    <x-form.actions>
                        <x-ui.button-secondary type="reset">Reset</x-ui.button-secondary>
                        <x-ui.button-primary type="submit">Save changes</x-ui.button-primary>
                    </x-form.actions>
                </form>
            </x-ui.card>

            <x-ui.card title="Danger zone">
                <x-ui.alert type="warning" class="mb-4">
                    Deleting your conversations removes prompts and responses from our database permanently.
                </x-ui.alert>
                <div class="flex items-center gap-3">
                    <x-ui.button-danger type="button">Delete all chats</x-ui.button-danger>
                    <span class="text-xs text-muted">We will ask for confirmation before permanently deleting your content.</span>
                </div>
            </x-ui.card>
        </div>
    </div>
</x-layout.page>
