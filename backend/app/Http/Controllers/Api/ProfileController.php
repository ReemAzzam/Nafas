<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\DeleteAccountRequest;
use App\Http\Requests\Api\Profile\RequestEmailChangeRequest;
use App\Http\Requests\Api\Profile\UpdateProfileImageRequest;
use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\Profile\VerifyEmailChangeRequest;
use App\Http\Resources\UserResource;
use App\Services\OtpService;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profiles) {}

    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث معلومات الحساب بنجاح.',
            'user' => UserResource::make(
                $this->profiles->update($request->user(), $request->validated())
            ),
        ]);
    }

    public function updateImage(UpdateProfileImageRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث صورة الحساب بنجاح.',
            'user' => UserResource::make(
                $this->profiles->updateImage($request->user(), $request->file('image'))
            ),
        ]);
    }

    public function deleteImage(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'تم حذف صورة الحساب بنجاح.',
            'user' => UserResource::make(
                $this->profiles->deleteImage($request->user())
            ),
        ]);
    }

    public function requestEmailChange(RequestEmailChangeRequest $request)
    {
        $email = $request->validated('email');
        $sent = $this->profiles->requestEmailChange(
            $request->user(),
            $email,
            $request->validated('password'),
        );

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى الانتظار قبل طلب رمز جديد.',
                'retry_after' => app(OtpService::class)->retryAfter($email, OtpService::EMAIL_CHANGE),
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال رمز التحقق إلى البريد الإلكتروني الجديد.',
            'expires_in' => OtpService::TTL_MINUTES * 60,
        ]);
    }

    public function verifyEmailChange(VerifyEmailChangeRequest $request)
    {
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير البريد الإلكتروني بنجاح.',
            'user' => UserResource::make(
                $this->profiles->verifyEmailChange(
                    $request->user(),
                    $validated['email'],
                    $validated['otp'],
                )
            ),
        ]);
    }

    public function destroy(DeleteAccountRequest $request)
    {
        $this->profiles->deleteAccount(
            $request->user(),
            $request->validated('password'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الحساب بنجاح.',
        ]);
    }
}
