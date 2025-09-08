<x-layouts.app :title="'Admin Control'">
    <div class="relative min-h-screen">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(16,185,129,0.08),transparent_60%)]"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-8">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Admin Control</h1>
                <div class="flex items-center gap-2">
                    <a href="/dashboard" class="text-sm text-emerald-500 hover:text-emerald-400">Back to Chat</a>
                </div>
            </div>

            <!-- Create User Panel -->
            <div class="mb-6 rounded-lg border border-slate-800/40 bg-panel/60 p-4 shadow-sm glass-panel dark:glass-panel">
                <h2 class="mb-3 text-lg font-medium">Create User</h2>
                <form id="create-user-form" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Name</label>
                        <input type="text" name="name" class="w-full rounded-md border border-slate-700/40 bg-black/20 px-3 py-2 outline-none focus:ring-1 focus:ring-emerald-500" placeholder="Jane Doe" required />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Email</label>
                        <input type="email" name="email" class="w-full rounded-md border border-slate-700/40 bg-black/20 px-3 py-2 outline-none focus:ring-1 focus:ring-emerald-500" placeholder="jane@example.com" required />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 text-slate-400">Role</label>
                        <select name="role" class="w-full rounded-md border border-slate-700/40 bg-black/20 px-3 py-2 outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="send_reset" class="rounded border-slate-700/40 bg-black/20" checked />
                            <span>Send set‑password email</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-between gap-3 pt-1">
                        <div id="create-user-result" class="text-sm"></div>
                        <button type="submit" class="rounded-md border border-emerald-500/40 px-3 py-2 text-sm font-medium text-emerald-400 hover:bg-emerald-500/10">Create</button>
                    </div>
                </form>
            </div>

            <div id="admin-users-panel" class="rounded-lg border border-slate-800/40 bg-panel/60 p-4 shadow-sm glass-panel dark:glass-panel">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-medium">Users</h2>
                    <div class="text-sm text-slate-400" id="user-count">Loading...</div>
                </div>

                <div class="overflow-auto rounded-md border border-slate-700/40">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200">
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
                        <tbody id="users-tbody" class="divide-y divide-slate-700/40">
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
</x-layouts.app>
