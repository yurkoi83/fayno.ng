<?php

namespace Laravel\Nova\Auth\Actions;

use Illuminate\Http\JsonResponse;
use Laravel\Nova\Nova;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $redirect = redirect()->intended(Nova::initialPathUrl($request));

        return $request->wantsJson()
            ? new JsonResponse([
                'redirect' => $redirect->getTargetUrl(),
            ], 200)
            : $redirect;
    }
}
