<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_published', 'remove_image'] as $field) {
            if ($this->exists($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $updating = $this->route('article') !== null;
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'slug' => [
                $presence,
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($this->route('article')),
            ],
            'summary' => [$presence, 'string', 'max:2000'],
            'content' => [$presence, 'string'],
            'author_name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'goal_ids' => ['sometimes', 'array'],
            'goal_ids.*' => ['integer', 'distinct', 'exists:goals,id'],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المقال مطلوب.',
            'slug.required' => 'معرّف المقال مطلوب.',
            'slug.regex' => 'معرّف المقال يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً وشرطات فقط.',
            'slug.unique' => 'معرّف المقال مستخدم مسبقاً.',
            'summary.required' => 'ملخص المقال مطلوب.',
            'content.required' => 'محتوى المقال مطلوب.',
            'goal_ids.*.exists' => 'أحد الأهداف المختارة غير موجود.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع jpeg أو png أو webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.',
        ];
    }
}
