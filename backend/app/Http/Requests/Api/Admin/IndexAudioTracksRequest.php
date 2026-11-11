<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAudioTracksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'published', 'deleted'])],
            'category' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'حالة المقطع غير صالحة.',
            'category.string' => 'تصنيف المقطع غير صالح.',
            'search.string' => 'عبارة البحث غير صالحة.',
            'per_page.integer' => 'عدد النتائج في الصفحة يجب أن يكون رقماً صحيحاً.',
            'per_page.min' => 'عدد النتائج في الصفحة يجب ألا يقل عن نتيجة واحدة.',
            'per_page.max' => 'عدد النتائج في الصفحة يجب ألا يتجاوز 100 نتيجة.',
        ];
    }
}
