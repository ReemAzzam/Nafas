<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    public function __construct(
        private ImageStorageService $images,
        private OtpService $otp,
    ) {}

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    public function updateImage(User $user, UploadedFile $image): User
    {
        $path = $this->images->storeUpload(
            $image,
            ImageStorageService::FOLDER_PROFILES,
            $user->profile_image_url,
        );

        $user->update(['profile_image_url' => $path]);

        return $user->fresh();
    }

    public function deleteImage(User $user): User
    {
        $this->images->delete($user->profile_image_url);
        $user->update(['profile_image_url' => null]);

        return $user->fresh();
    }

    public function requestEmailChange(User $user, string $email, string $password): bool
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $sent = $this->otp->sendToEmail($email, OtpService::EMAIL_CHANGE);

        if ($sent) {
            Cache::put(
                $this->pendingEmailKey($user),
                $email,
                now()->addMinutes(OtpService::TTL_MINUTES),
            );
        }

        return $sent;
    }

    public function verifyEmailChange(User $user, string $email, string $otp): User
    {
        $pendingEmail = Cache::get($this->pendingEmailKey($user));

        if ($pendingEmail !== $email || ! $this->otp->check($email, $otp, OtpService::EMAIL_CHANGE)) {
            throw ValidationException::withMessages([
                'otp' => ['رمز التحقق غير صحيح أو انتهت صلاحيته.'],
            ]);
        }

        DB::transaction(function () use ($user, $email) {
            $user->forceFill([
                'email' => $email,
                'email_verified_at' => now(),
            ])->save();

            $currentTokenId = $user->currentAccessToken()?->getKey();

            $user->tokens()
                ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
                ->delete();
        });

        $this->otp->forget($email, OtpService::EMAIL_CHANGE);
        Cache::forget($this->pendingEmailKey($user));

        return $user->fresh();
    }

    public function deleteAccount(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $pendingEmail = Cache::get($this->pendingEmailKey($user));

            $user->tokens()->delete();
            \App\Models\SupportMessage::where('user_id', $user->id)->delete();
            \App\Models\WellnessProfile::where('user_id', $user->id)->delete();
            \App\Models\RecommendationFeedback::where('user_id', $user->id)->delete();
            \App\Models\StudyPlan::where('user_id', $user->id)->delete();
            \App\Models\ReminderPreference::where('user_id', $user->id)->delete();
            $user->delete();

            foreach ([OtpService::REGISTER, OtpService::RESET] as $purpose) {
                $this->otp->forget($user->email, $purpose);
            }

            if ($pendingEmail) {
                $this->otp->forget($pendingEmail, OtpService::EMAIL_CHANGE);
            }

            Cache::forget($this->pendingEmailKey($user));
        });
    }

    public function pendingEmailKey(User $user): string
    {
        return 'profile_email_change_user_'.$user->id;
    }
}
