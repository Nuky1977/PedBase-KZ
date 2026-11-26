<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends FortifyController
{
    public function store(SendPasswordResetLinkRequest $request): Responsable
    {
        if (User::catalogPasswordDeliveryEnabled()) {
            try {
                return parent::store($request);
            } catch (TransportExceptionInterface) {
                // Provider diagnostics may contain SMTP or message data.
            }
        }

        throw ValidationException::withMessages(['email' => __('passwords.delivery_unavailable')]);
    }
}
