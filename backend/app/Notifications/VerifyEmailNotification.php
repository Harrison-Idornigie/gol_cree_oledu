<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Facades\Tenancy;

class VerifyEmailNotification extends Notification
{
    /**
     * The callback that should be used to create the verify email URL.
     *
     * @var \Closure|null
     */
    public static $createUrlCallback;

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Email Address')
            ->markdown('emails.auth.verify-email', ['verificationUrl' => $verificationUrl]);
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        // Generate frontend URL for email verification
        // The frontend will extract the parameters and call the backend API
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $hash = sha1($notifiable->getEmailForVerification());
        $expires = Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60))->timestamp;

        // Check if we're in a tenant context
        if (Tenancy::initialized()) {
            $tenant = tenant();

            // Generate tenant-aware frontend verification URL
            // Frontend route: /{tenant}/verify-email?id={id}&hash={hash}&expires={expires}&signature={signature}
            $url = "{$frontendUrl}/{$tenant->slug}/verify-email";
        } else {
            // Central verification URL
            // Frontend route: /verify-email?id={id}&hash={hash}&expires={expires}&signature={signature}
            $url = "{$frontendUrl}/verify-email";
        }

        // Generate signature for URL verification (same as Laravel's signed URLs)
        $signature = hash_hmac(
            'sha256',
            "id={$notifiable->getKey()}&hash={$hash}&expires={$expires}",
            config('app.key')
        );

        return "{$url}?id={$notifiable->getKey()}&hash={$hash}&expires={$expires}&signature={$signature}";
    }

    /**
     * Set a callback that should be used when creating the email verification URL.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function createUrlUsing($callback)
    {
        static::$createUrlCallback = $callback;
    }
}
