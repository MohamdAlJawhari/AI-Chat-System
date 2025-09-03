<footer class="p-4 border-t border-slate-200 dark:border-neutral-800">
    <div class="max-w-3xl mx-auto w-full">
        <div class="text-right text-[11px] text-violet-500 mb-1">Enter To Send Prompt</div>
        <div class="relative flex items-center">
            <div class="flex-1 rounded-full bg-slate-100 dark:bg-neutral-900 border border-slate-300 dark:border-neutral-700 shadow-inner px-4 py-2 flex items-center gap-2">
                <i class="fa-regular fa-pen-to-square text-slate-400"></i>
                <textarea id="composer" dir="auto" rows="1" placeholder="Type your prompt here..." class="flex-1 resize-none bg-transparent outline-none text-sm leading-6 placeholder:text-slate-400 dark:placeholder:text-neutral-500"></textarea>
            </div>
            <button id="sendBtn" class="ml-3 h-10 w-10 rounded-full bg-slate-900 text-white dark:bg-white dark:text-black shadow-md flex items-center justify-center hover:opacity-90">
                <i class="fa-regular fa-paper-plane"></i>
            </button>
        </div>
        <div class="text-xs text-neutral-500 mt-2">Enter to send • Shift+Enter for newline</div>
    </div>
</footer>
