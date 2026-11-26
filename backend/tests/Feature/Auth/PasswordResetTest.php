<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;

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

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');

    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('unconfigured Postmark rejects reset requests before looking up an account', function () {
    Notification::fake();
    config([
        'mail.default' => 'postmark-smtp',
        'mail.mailers.postmark-smtp.username' => 'synthetic-id',
        'mail.mailers.postmark-smtp.password' => null,
    ]);

    $user = User::factory()->create();
    foreach ([$user->email, 'missing@example.test'] as $email) {
        $this->postJson(route('password.email'), ['email' => $email])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', __('passwords.delivery_unavailable'));
    }

    Notification::assertNothingSent();
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('transport errors return a localized message without exposing SMTP details', function () {
    Event::listen(NotificationSending::class, function (): never {
        throw new TransportException('synthetic provider detail');
    });
    $user = User::factory()->create();

    $this->postJson(route('password.email'), ['email' => $user->email])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', __('passwords.delivery_unavailable'))
        ->assertDontSee('synthetic provider detail');
});

test('password setup mail renders in the request language and uses the configured HTTPS origin', function (string $locale, string $subject, string $action) {
    Notification::fake();
    config(['app.url' => 'https://staging.example.test']);
    $user = User::factory()->create(['catalog_onboarding_pending' => true]);

    $this->withCookie('pedbase_locale', $locale)->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors();

    $notification = Notification::sent($user, ResetPassword::class)->sole();
    $message = $notification->toMail($user);
    expect($message->subject)->toBe($subject)
        ->and($message->actionText)->toBe($action)
        ->and($message->actionUrl)->toStartWith('https://staging.example.test/reset-password/')
        ->and((string) $message->render())->toContain($action);
})->with([
    ['kk', 'Құпиясөзді орнату немесе қалпына келтіру', 'Құпиясөз орнату'],
    ['ru', 'Установка или сброс пароля', 'Установить пароль'],
    ['en', 'Set or reset your password', 'Set password'],
]);

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
