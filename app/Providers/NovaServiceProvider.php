<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\NovaApplicationServiceProvider;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);

        Gate::policy(
            $registrar->getRoleClass(),
            \App\Nova\Users\RolePolicy::class
        );

        Gate::policy(
            $registrar->getPermissionClass(),
            \App\Nova\Users\PermissionPolicy::class
        );

        Nova::resources([
            \App\Nova\Camps\Camp::class,
            \App\Nova\Camps\CampShift::class,
            \App\Nova\Bookings\Order::class,
            \App\Nova\Bookings\Booking::class,
            \App\Nova\Bookings\OrderCamper::class,
            \App\Nova\Bookings\OrderStatusHistory::class,
            \App\Nova\People\Camper::class,
            \App\Nova\People\Customer::class,
            \App\Nova\Finance\Payment::class,
            \App\Nova\Settings\Setting::class,
        ]);

        Nova::mainMenu(function (Request $request) {
            return [
                MenuItem::dashboard(\App\Nova\Dashboards\Main::class)->name(__('nova::navigation.overview')),
                MenuItem::resource(\App\Nova\Camps\Camp::class)->name(__('nova::navigation.camps')),
                MenuItem::resource(\App\Nova\Bookings\Order::class)->name(__('nova::navigation.bookings')),
                MenuItem::resource(\App\Nova\People\Camper::class)->name(__('nova::navigation.campers')),
                MenuItem::resource(\App\Nova\People\Customer::class)->name(__('nova::navigation.parents')),
                MenuItem::resource(\App\Nova\Finance\Payment::class)->name(__('nova::navigation.payments')),
                MenuSection::make(__('nova::navigation.content'), [
                    MenuItem::resource(\App\Nova\Page::class)->name(__('nova::resources.Pages')),
                    MenuItem::resource(\App\Nova\Menu::class)->name(__('nova::resources.Menus')),
                ])->icon('document-text')->collapsable(),
                MenuItem::resource(\App\Nova\Settings\Setting::class)->name(__('nova::navigation.settings')),
                MenuSection::make(__('nova::navigation.access'), [
                    MenuItem::resource(\App\Nova\Users\User::class),
                    MenuItem::resource(\App\Nova\Users\Role::class),
                    MenuItem::resource(\App\Nova\Users\Permission::class),
                ])->icon('user-group')->collapsable(),
            ];
        });

        Nova::serving(function (ServingNova $event) {
            Nova::style(
                'fayno-theme',
                asset('js/fayno-admin.css') . '?v=' . (file_exists(public_path('js/fayno-admin.css')) ? filemtime(public_path('js/fayno-admin.css')) : time())
            );

            Nova::script(
                'fayno-admin',
                asset('js/fayno-admin.js') . '?v=' . filemtime(public_path('js/fayno-admin.js'))
            );
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
                // Features::passkeys(),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes()
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return in_array($user->email, [
                //
            ]);
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, \Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }
}
