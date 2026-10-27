<?php

namespace Laravel\Nova\Http\Controllers\Pages;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Nova\Auth\Concerns\ConfirmsTwoFactorAuthentication;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Laravel\Nova\Util;

class UserSecurityController extends Controller
{
    use ConfirmsTwoFactorAuthentication;

    /**
     * Show User Security page.
     */
    public function show(NovaRequest $request): Response
    {
        abort_unless(Features::hasSecurityFeatures(), 404);

        $this->validateTwoFactorAuthenticationState($request);

        $features = [];

        if (Util::hasPasskeysAuthentication()) {
            $features['passkeys'] = Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [];
        }

        return Inertia::render('Nova.UserSecurity', [
            'options' => config('fortify-options', []),
            'user' => transform(Nova::user($request), static fn ($user) => [
                'two_factor_enabled' => Features::enabled(Features::twoFactorAuthentication())
                    && ! \is_null($user->two_factor_secret), // @phpstan-ignore property.notFound
            ]),
            ...$features,
        ]);
    }
}
