<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SendOTPNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class OtpService
{
    public const REGISTER = 'register';

    public const RESET = 'reset';

    public const EMAIL_CHANGE = 'email_change';

    public const TTL_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    private function cooldownKey(string $purpose, string $email): string
    {
        return "otp_cooldown_{$purpose}_{$email}";
    }

    public function send(User $user, string $purpose): bool
    {
        return $this->sendToEmail($user->email, $purpose, $user);
    }

    public function sendToEmail(string $email, string $purpose, ?User $user = null): bool
    {
        $email = strtolower(trim($email));
        $cooldownKey = $this->cooldownKey($purpose, $email);

        if (Cache::has($cooldownKey)) {
            return false;
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put($this->key($purpose, $email), hash('sha256', $otp), now()->addMinutes(self::TTL_MINUTES));
        Cache::put($this->attemptsKey($purpose, $email), 0, now()->addMinutes(self::TTL_MINUTES));
        Cache::put($cooldownKey, 1, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS));

        dispatch(function () use ($user, $email, $otp, $purpose) {
            if ($user && $user->email === $email) {
                $user->notify(new SendOTPNotification($otp, $purpose));

                return;
            }

            Notification::route('mail', $email)
                ->notify(new SendOTPNotification($otp, $purpose));
        })->afterResponse();

        return true;
    }

    public function retryAfter(string $email, string $purpose): int
    {
        $email = strtolower(trim($email));
        $ttl = Cache::get($this->cooldownKey($purpose, $email));

        return Cache::has($this->cooldownKey($purpose, $email))
            ? (int) Cache::getTimeToLive($this->cooldownKey($purpose, $email))
            : 0;
    }

    public function check(string $email, string $otp, string $purpose): bool
    {
        $email = strtolower(trim($email));
        $cached = Cache::get($this->key($purpose, $email));
        $attempts = (int) Cache::get($this->attemptsKey($purpose, $email), 0);

        if (! $cached || $attempts >= self::MAX_ATTEMPTS) {
            $this->forget($email, $purpose);

            return false;
        }

        if (! hash_equals((string) $cached, hash('sha256', (string) $otp))) {
            Cache::put(
                $this->attemptsKey($purpose, $email),
                $attempts + 1,
                now()->addMinutes(self::TTL_MINUTES)
            );

            return false;
        }

        return true;
    }

    public function forget(string $email, string $purpose): void
    {
        $email = strtolower(trim($email));
        Cache::forget($this->key($purpose, $email));
        Cache::forget($this->attemptsKey($purpose, $email));
        Cache::forget($this->cooldownKey($purpose, $email));
    }

    private function key(string $purpose, string $email): string
    {
        return "otp_{$purpose}_{$email}";
    }

    private function attemptsKey(string $purpose, string $email): string
    {
        return "otp_attempts_{$purpose}_{$email}";
    }
}
