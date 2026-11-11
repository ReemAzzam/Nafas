<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->email) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.size' => 'رمز التحقق يجب أن يتكون من 6 أرقام.',
            'password.required' => 'كلمة السر مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة السر غير متطابق.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
