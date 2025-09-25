<x-layout.page :title="'Admin Control'">
    <div class="relative min-h-screen">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.08),transparent_60%)]"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Admin Control</h1>
                <div class="flex items-center gap-2">
                    <a href="/dashboard" class="text-sm text-emerald-500 hover:text-emerald-400">Back to Chat</a>
                </div>
            </div>

            <div class="mb-6 rounded-lg p-4 shadow-sm glass-panel">
                <h2 class="mb-3 text-lg font-medium">Create User</h2>
                <form id="create-user-form" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Name</label>
                        <input type="text" name="name" class="w-full rounded-md px-3 py-2 outline-none focus:ring-1" style="background: var(--surface); border: 1px solid var(--border-muted)" placeholder="Jane Doe" required />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Email</label>
                        <input type="email" name="email" class="w-full rounded-md px-3 py-2 outline-none focus:ring-1" style="background: var(--surface); border: 1px solid var(--border-muted)" placeholder="jane@example.com" required />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Role</label>
                        <select name="role" class="w-full rounded-md px-3 py-2 outline-none focus:ring-1" style="background: var(--surface); border: 1px solid var(--border-muted)">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="send_reset" class="rounded" style="border: 1px solid var(--border-muted); background: var(--surface)" checked />
                            <span>Send set‑password email</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-between gap-3 pt-1">
                        <div id="create-user-result" class="text-sm"></div>
                        <button type="submit" class="rounded-md px-3 py-2 text-sm font-medium text-white" style="background: var(--accent)">Create</button>
                    </div>
                </form>
            </div>

            <div id="admin-users-panel" class="rounded-lg p-4 shadow-sm glass-panel">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-medium">Users</h2>
                    <div class="text-sm text-muted" id="user-count">Loading...</div>
                </div>

                <div class="overflow-auto rounded-md border" style="border-color: var(--border-muted)">
                    <table class="min-w-full text-sm">
                        <thead style="background: var(--surface); color: var(--text)">
                            <tr>
                                <th class="px-3 py-2 text-left">ID</th>
                                <th class="px-3 py-2 text-left">Name</th>
                                <th class="px-3 py-2 text-left">Email</th>
                                <th class="px-3 py-2 text-left">Role</th>
                                <th class="px-3 py-2 text-left">Blocked</th>
                                <th class="px-3 py-2 text-left">Created</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-tbody" class="divide-y" style="--tw-divide-opacity: 1; border-color: var(--border-muted)">
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-slate-500">Loading users…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        @php($__ver = @filemtime(public_path('js/admin/users.js')) ?: time())
        <script type="module" src="/js/admin/users.js?v={{ $__ver }}"></script>
    </x-slot>
</x-layout.page>
