<?php

namespace App\Http\Requests\Api\Profile;

class UpdateProfileImageRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'يرجى اختيار صورة.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع jpeg أو png أو webp.',
            'image.mimetypes' => 'ملف الصورة غير صالح.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.',
        ];
    }
}
