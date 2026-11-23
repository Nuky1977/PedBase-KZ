<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if ($user->catalog_onboarding_pending && ! $user->catalogPasswordDeliveryEnabled()) {
            throw ValidationException::withMessages(['email' => 'Пошта жеткізілімі қолжетімсіз. Әкімшіге хабарласыңыз.']);
        }
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $values = ['password' => $input['password']];
        if ($user->catalog_onboarding_pending) {
            $values['catalog_onboarding_pending'] = false;
        }
        $user->forceFill($values)->save();
    }
}
