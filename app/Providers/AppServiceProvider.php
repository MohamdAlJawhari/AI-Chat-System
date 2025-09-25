<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::component('components.ui.application-logo', 'application-logo');
        Blade::component('components.ui.session-status', 'auth-session-status');
        Blade::component('components.ui.button-danger', 'danger-button');
        Blade::component('components.ui.dropdown-link', 'dropdown-link');
        Blade::component('components.ui.dropdown', 'dropdown');
        Blade::component('components.form.error', 'input-error');
        Blade::component('components.form.label', 'input-label');
        Blade::component('components.ui.modal', 'modal');
        Blade::component('components.layout.nav-link', 'nav-link');
        Blade::component('components.ui.button-primary', 'primary-button');
        Blade::component('components.layout.responsive-nav-link', 'responsive-nav-link');
        Blade::component('components.ui.button-secondary', 'secondary-button');
        Blade::component('components.form.input', 'text-input');
    }
}
