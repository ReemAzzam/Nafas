<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Validation\Rule;

class VerifyEmailChangeRequest extends ProfileFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->email ? strtolower(trim($this->email)) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني الجديد مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مستخدم في حساب نشط آخر.',
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.size' => 'رمز التحقق يجب أن يتكون من 6 أرقام.',
        ];
    }
}
