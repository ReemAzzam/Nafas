<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSosHotlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('is_active')) {
            $this->merge(['is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function rules(): array
    {
        $presence = $this->route('hotline') ? 'sometimes' : 'required';

        return [
            'slug' => [
                $presence,
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('sos_hotlines', 'slug')->ignore($this->route('hotline')),
            ],
            'title' => [$presence, 'string', 'max:255'],
            'phone' => [$presence, 'string', 'regex:/^[0-9]{2,10}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الخط الساخن يجب أن يحتوي أرقاماً فقط بطول من 2 إلى 10 خانات.',
        ];
    }
}
