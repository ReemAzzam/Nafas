<?php

namespace App\Http\Requests\Api\Profile;

class DeleteAccountRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'كلمة المرور الحالية مطلوبة لتأكيد حذف الحساب.',
        ];
    }
}
