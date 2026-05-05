<?php

namespace FernandoGuiao\StatelessTenancy\Traits;

use FernandoGuiao\StatelessTenancy\Notifications\StatelessActionNotification;
use FernandoGuiao\StatelessTenancy\Services\AuthService;

trait SendsStatelessNotifications
{
    /**
     * Sends a stateless password reset notification.
     */
    public function sendStatelessPasswordResetNotification(string $frontendResetUrl): void
    {
        $authService = app(AuthService::class);
        $token = $authService->issueActionToken($this, 'password_reset', 15);

        // Build the URL
        $separator = parse_url($frontendResetUrl, PHP_URL_QUERY) ? '&' : '?';
        $actionUrl = $frontendResetUrl . $separator . 'token=' . $token;

        $this->notify(new StatelessActionNotification(
            __('Reset Password Notification'),
            [
                __('You are receiving this email because we received a password reset request for your account.'),
            ],
            __('Reset Password'),
            $actionUrl
        ));
    }

    /**
     * Sends a stateless email verification notification.
     */
    public function sendStatelessEmailVerificationNotification(string $frontendVerifyUrl): void
    {
        $authService = app(AuthService::class);
        $token = $authService->issueActionToken($this, 'email_verification', 60);

        // Build the URL
        $separator = parse_url($frontendVerifyUrl, PHP_URL_QUERY) ? '&' : '?';
        $actionUrl = $frontendVerifyUrl . $separator . 'token=' . $token;

        $this->notify(new StatelessActionNotification(
            __('Verify Email Address'),
            [
                __('Please click the button below to verify your email address.'),
            ],
            __('Verify Email Address'),
            $actionUrl
        ));
    }
}
