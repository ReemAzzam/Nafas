<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertExerciseRequest extends FormRequest
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

        if (is_string($this->input('content_steps'))) {
            $decoded = json_decode($this->input('content_steps'), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['content_steps' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $updating = $this->route('exercise') !== null;
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'slug' => [
                $presence,
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('exercises', 'slug')->ignore($this->route('exercise')),
            ],
            'title' => [$presence, 'string', 'max:255'],
            'description' => [$presence, 'string', 'max:5000'],
            'type' => [$presence, Rule::in(['breathing', 'grounding', 'journaling', 'relaxation'])],
            'duration_minutes' => [$presence, 'integer', 'min:1', 'max:120'],
            'content_steps' => [$presence, 'array', 'min:1'],
            'content_steps.*.title' => ['required', 'string', 'max:255'],
            'content_steps.*.body' => ['required', 'string', 'max:2000'],
            'media_type' => ['nullable', 'required_with:media_url', Rule::in(['video', 'audio'])],
            'media_url' => ['nullable', 'url:http,https', 'max:2048'],
            'media_credit' => ['nullable', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'goal_ids' => ['sometimes', 'array'],
            'goal_ids.*' => ['integer', 'distinct', 'exists:goals,id'],
            'optional_audio_track_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('audio_tracks', 'id')->whereNull('deleted_at'),
            ],
            'image' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => 'معرّف التمرين مطلوب.',
            'slug.regex' => 'معرّف التمرين يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً وشرطات فقط.',
            'slug.unique' => 'معرّف التمرين مستخدم مسبقاً.',
            'title.required' => 'عنوان التمرين مطلوب.',
            'description.required' => 'وصف التمرين مطلوب.',
            'type.in' => 'نوع التمرين غير صالح.',
            'duration_minutes.required' => 'مدة التمرين مطلوبة.',
            'content_steps.required' => 'خطوات التمرين مطلوبة.',
            'content_steps.*.title.required' => 'عنوان كل خطوة مطلوب.',
            'content_steps.*.body.required' => 'تفاصيل كل خطوة مطلوبة.',
            'media_type.required_with' => 'نوع الوسائط مطلوب عند إضافة رابط.',
            'media_type.in' => 'نوع الوسائط يجب أن يكون فيديو أو صوتاً.',
            'media_url.url' => 'رابط الوسائط غير صالح.',
            'optional_audio_track_id.exists' => 'المقطع الصوتي الاختياري غير موجود.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع jpeg أو png أو webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.',
        ];
    }
}
