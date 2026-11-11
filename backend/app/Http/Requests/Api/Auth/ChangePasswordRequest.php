<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'كلمة السر الحالية مطلوبة.',
            'password.required' => 'كلمة السر الجديدة مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة السر غير متطابق.',
            'password.different' => 'كلمة السر الجديدة يجب أن تختلف عن الحالية.',
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
