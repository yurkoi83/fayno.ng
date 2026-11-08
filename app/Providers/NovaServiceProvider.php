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
            \App\Nova\People\Camper::class,
            \App\Nova\People\Customer::class,
            \App\Nova\Finance\Payment::class,
            \App\Nova\Settings\Setting::class,
        ]);

        Nova::mainMenu(function (Request $request) {
            return [
                MenuItem::dashboard(\App\Nova\Dashboards\Main::class),
                MenuItem::resource(\App\Nova\Camps\Camp::class),
                MenuItem::resource(\App\Nova\Camps\CampShift::class),
                MenuItem::resource(\App\Nova\Bookings\Order::class),
                MenuItem::resource(\App\Nova\Bookings\Booking::class),
                MenuItem::resource(\App\Nova\People\Camper::class),
                MenuItem::resource(\App\Nova\People\Customer::class),
                MenuItem::resource(\App\Nova\Finance\Payment::class),
                MenuItem::resource(\App\Nova\Page::class),
                MenuItem::resource(\App\Nova\Menu::class),
                MenuItem::resource(\App\Nova\Settings\Setting::class),
                MenuSection::make('Доступ и пользователи', [
                    MenuItem::resource(\App\Nova\Users\User::class),
                    MenuItem::resource(\App\Nova\Users\Role::class),
                    MenuItem::resource(\App\Nova\Users\Permission::class),
                ])->icon('user-group')->collapsable(),
            ];
        });

        Nova::serving(function (ServingNova $event) {
            Nova::style(
                'fayno-theme',
                asset('js/fayno-admin.css')
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
