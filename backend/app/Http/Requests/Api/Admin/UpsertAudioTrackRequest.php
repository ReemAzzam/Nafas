<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAudioTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('remove_image')) {
            $this->merge([
                'remove_image' => filter_var($this->input('remove_image'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        $updating = $this->route('audioTrack') !== null;
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'slug' => [
                $presence,
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('audio_tracks', 'slug')->ignore($this->route('audioTrack')),
            ],
            'description' => [$presence, 'string', 'max:5000'],
            'category' => [$presence, 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'duration_seconds' => [
                Rule::requiredIf(! $updating || $this->hasFile('audio')),
                'integer',
                'min:1',
                'max:14400',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'credit' => ['sometimes', 'required', 'string', 'max:255'],
            'audio' => [
                Rule::requiredIf(! $updating),
                'file',
                'extensions:m4a',
                'mimetypes:audio/mp4,video/mp4,audio/x-m4a',
                'max:35840',
            ],
            'image' => [
                'sometimes',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
            ],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان المقطع مطلوب.',
            'slug.required' => 'معرّف المقطع مطلوب.',
            'slug.regex' => 'معرّف المقطع يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً وشرطات فقط.',
            'slug.unique' => 'معرّف المقطع مستخدم مسبقاً.',
            'description.required' => 'وصف المقطع مطلوب.',
            'category.required' => 'تصنيف المقطع مطلوب.',
            'category.regex' => 'تصنيف المقطع يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً وشرطات فقط.',
            'duration_seconds.required' => 'مدة المقطع بالثواني مطلوبة.',
            'duration_seconds.integer' => 'مدة المقطع يجب أن تكون رقماً صحيحاً بالثواني.',
            'duration_seconds.min' => 'مدة المقطع يجب ألا تقل عن ثانية واحدة.',
            'duration_seconds.max' => 'مدة المقطع يجب ألا تتجاوز أربع ساعات.',
            'audio.required' => 'الملف الصوتي مطلوب.',
            'audio.extensions' => 'امتداد الملف الصوتي يجب أن يكون m4a.',
            'audio.mimetypes' => 'صيغة الملف الصوتي غير مدعومة.',
            'audio.max' => 'يجب ألا يتجاوز حجم الملف الصوتي 35 ميغابايت.',
            'image.mimes' => 'الصورة يجب أن تكون من نوع jpeg أو png أو webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميغابايت.',
        ];
    }
}
