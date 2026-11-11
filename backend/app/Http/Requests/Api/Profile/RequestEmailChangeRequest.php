<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends ProfileFormRequest
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
            'password' => ['required', 'string'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::notIn([$this->user()->email]),
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'كلمة المرور الحالية مطلوبة لتغيير البريد الإلكتروني.',
            'email.required' => 'البريد الإلكتروني الجديد مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مستخدم في حساب نشط آخر.',
            'email.not_in' => 'البريد الإلكتروني الجديد مطابق لبريدك الحالي.',
        ];
    }
}
