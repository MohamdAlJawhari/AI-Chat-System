<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm" style="color: color-mix(in srgb, var(--text) 80%, transparent)">
                <input id="remember_me" type="checkbox" class="rounded" style="border:1px solid var(--border-muted); background: var(--surface)" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm underline" style="color: color-mix(in srgb, var(--text) 80%, transparent)" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <div class="flex items-center justify-between">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="inline-flex items-center rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-widest" style="background: var(--surface); color: var(--text); border: 1px solid var(--border-muted)">
                    {{ __('Create account') }}
                </a>
            @endif
            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
