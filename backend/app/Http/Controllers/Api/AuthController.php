<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResendOtpRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /**
     * Register the user (Full info or Anonymously)
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'name' => $validated['is_anonymous'] ? 'مستخدم مجهول' : $validated['name'],
                    'display_name' => $validated['display_name'] ?? null,
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'country_code' => $validated['country_code'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'status' => $validated['status'] ?? null,
                    'is_anonymous' => $validated['is_anonymous'],
                ]);
            });

            $user->refresh();

            $this->otp->send($user, OtpService::REGISTER);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الحساب بنجاح. يرجى إدخال رمز التحقق المرسل لإيميلك.',
                'user' => UserResource::make($user),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التسجيل، يرجى المحاولة لاحقاً.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify OTP and activate the account
     */
    public function verifyOtp(VerifyOtpRequest $request)
    {
        $validated = $request->validated();
        $email = $validated['email'];

        if (! $this->otp->check($email, $validated['otp'], OtpService::REGISTER)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو انتهت صلاحيته.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user || $user->is_blocked) {
            $this->otp->forget($email, OtpService::REGISTER);

            return response()->json([
                'success' => false,
                'message' => $user?->is_blocked
                    ? 'تم حظر هذا الحساب لمخالفة الشروط.'
                    : 'رمز التحقق غير صحيح أو انتهت صلاحيته.',
            ], $user?->is_blocked ? 403 : 400);
        }

        $user->email_verified_at = now();
        $user->save();

        $this->otp->forget($email, OtpService::REGISTER);

        $token = $user->createToken('nafas_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل الحساب بنجاح، أهلاً بك في تطبيق نفس.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Resend OTP via Email
     */
    public function resendOtp(ResendOtpRequest $request)
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب مفعل بالفعل.',
            ], 400);
        }

        $this->otp->send($user, OtpService::REGISTER);

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة إرسال رمز التحقق الجديد إلى بريدك الإلكتروني.',
        ]);
    }

    /**
     * Login the user and create token
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'يرجى تفعيل البريد الإلكتروني أولاً.',
                'requires_otp' => true,
            ], 403);
        }

        if ($user->is_blocked) {
            return response()->json([
                'success' => false,
                'message' => 'تم حظر هذا الحساب لمخالفة الشروط.',
            ], 403);
        }

        $token = $user->createToken('nafas_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'token' => $token,
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Logout the user (Revoke the token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->validated()['email'];
        $user = User::where('email', $email)->first();

        if ($user && ! $user->is_blocked) {
            try {
                $this->otp->send($user, OtpService::RESET);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'إذا كان البريد مسجلاً لدينا، فقد أرسلنا رمز استعادة كلمة السر.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $validated = $request->validated();

        if (! $this->otp->check($validated['email'], $validated['otp'], OtpService::RESET)) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو انتهت صلاحيته.',
            ], 400);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || $user->is_blocked) {
            $this->otp->forget($validated['email'], OtpService::RESET);

            return response()->json([
                'success' => false,
                'message' => 'تعذر تحديث كلمة السر.',
            ], 400);
        }

        $user->password = $validated['password']; // hashed cast
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        $user->tokens()->delete(); // ألغِ كل الجلسات القديمة
        $this->otp->forget($validated['email'], OtpService::RESET);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كلمة السر بنجاح، يمكنك تسجيل الدخول.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة السر الحالية غير صحيحة.',
            ], 400);
        }

        $user->password = $validated['password'];
        $user->save();

        // ألغِ باقي الأجهزة، خليه مسجّل على الجهاز الحالي
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة السر بنجاح.',
        ]);
    }
}
