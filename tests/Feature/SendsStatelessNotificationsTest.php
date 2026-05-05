<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Feature;

use FernandoGuiao\StatelessTenancy\Notifications\StatelessActionNotification;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\User;
use FernandoGuiao\StatelessTenancy\Tests\TestCase;
use FernandoGuiao\StatelessTenancy\Traits\SendsStatelessNotifications;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class NotifiableUser extends User
{
    use Notifiable, SendsStatelessNotifications;
    protected $table = 'users';
}

class SendsStatelessNotificationsTest extends TestCase
{
    public function test_it_can_send_stateless_password_reset_notification()
    {
        Notification::fake();

        $user = NotifiableUser::create(['name' => 'Notification User', 'email' => 'notify@test.com', 'password' => Hash::make('password')]);

        $user->sendStatelessPasswordResetNotification('https://example.com/reset');

        Notification::assertSentTo(
            $user,
            StatelessActionNotification::class,
            function (StatelessActionNotification $notification) use ($user) {
                $mailData = $notification->toMail($user);

                // Assert it generates a valid URL containing the token
                return str_contains($mailData->actionUrl, 'https://example.com/reset?token=') &&
                       $mailData->subject === __('Reset Password Notification');
            }
        );
    }

    public function test_it_can_send_stateless_email_verification_notification()
    {
        Notification::fake();

        $user = NotifiableUser::create(['name' => 'Verify User', 'email' => 'verify@test.com', 'password' => Hash::make('password')]);

        $user->sendStatelessEmailVerificationNotification('https://example.com/verify?test=1');

        Notification::assertSentTo(
            $user,
            StatelessActionNotification::class,
            function (StatelessActionNotification $notification) use ($user) {
                $mailData = $notification->toMail($user);

                // Assert it generates a valid URL containing the token with ampersand for existing query params
                return str_contains($mailData->actionUrl, 'https://example.com/verify?test=1&token=') &&
                       $mailData->subject === __('Verify Email Address');
            }
        );
    }
}
