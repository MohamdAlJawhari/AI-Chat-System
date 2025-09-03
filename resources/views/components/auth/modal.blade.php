<div id="authModal" class="hidden fixed inset-0 z-40 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-neutral-700 shadow-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex gap-2" role="tablist">
                <button id="authLoginTab" class="px-3 py-1 rounded-md bg-slate-200 dark:bg-neutral-800">Login</button>
                <button id="authSignupTab" class="px-3 py-1 rounded-md">Sign Up</button>
            </div>
            <button id="authCloseBtn" class="text-slate-500 hover:text-slate-700 dark:text-neutral-400 dark:hover:text-neutral-200"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="space-y-3">
            <div id="authNameRow" class="hidden">
                <label class="block text-xs mb-1">Name</label>
                <input id="authName" type="text" class="w-full rounded-md bg-white dark:bg-neutral-800 border border-slate-300 dark:border-neutral-700 px-3 py-2 text-sm" placeholder="Your name" />
            </div>
            <div>
                <label class="block text-xs mb-1">Email</label>
                <input id="authEmail" type="email" class="w-full rounded-md bg-white dark:bg-neutral-800 border border-slate-300 dark:border-neutral-700 px-3 py-2 text-sm" placeholder="you@example.com" />
            </div>
            <div>
                <label class="block text-xs mb-1">Password</label>
                <input id="authPassword" type="password" class="w-full rounded-md bg-white dark:bg-neutral-800 border border-slate-300 dark:border-neutral-700 px-3 py-2 text-sm" placeholder="••••••" />
            </div>
            <div id="authError" class="hidden text-sm text-red-600"></div>
            <div class="pt-2 flex items-center justify-end gap-2">
                <button id="authSubmitBtn" class="rounded-md bg-slate-900 text-white dark:bg-white dark:text-black px-4 py-2 text-sm">Continue</button>
            </div>
        </div>
    </div>
  </div>

