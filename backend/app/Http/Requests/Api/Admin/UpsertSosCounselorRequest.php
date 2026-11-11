<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSosCounselorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_active', 'remove_image'] as $field) {
            if ($this->exists($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $presence = $this->route('counselor') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'specialty' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => [$presence, 'string', 'phone:SY,mobile'],
            'address' => [$presence, 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lat' => [$presence, 'numeric', 'between:-90,90'],
            'lng' => [$presence, 'numeric', 'between:-180,180'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.phone' => 'يجب أن يكون الرقم موبايل سوري صالح.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع jpeg أو png أو webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.',
        ];
    }
}
