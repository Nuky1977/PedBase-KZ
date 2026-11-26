<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the log mailer never receives a password reset notification for an existing account', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $user = User::factory()->create(['catalog_onboarding_pending' => false]);

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('an expired password setup token cannot change the account password', function () {
    Notification::fake();
    $user = User::factory()->create(['catalog_onboarding_pending' => true]);
    $originalHash = $user->getAuthPassword();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    $this->post(route('password.update'), [
        'token' => $notification->token,
        'email' => $user->email,
        'password' => 'synthetic-test-password',
        'password_confirmation' => 'synthetic-test-password',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->getAuthPassword())->toBe($originalHash);
    expect((bool) $user->fresh()->catalog_onboarding_pending)->toBeTrue();
});
