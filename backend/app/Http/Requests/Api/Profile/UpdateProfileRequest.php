<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

class UpdateProfileRequest extends ProfileFormRequest
{
    private ?string $e164Phone = null;

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'display_name'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $data[$field] = trim($this->input($field));
            }
        }

        $hasPhone = $this->exists('phone');
        $hasCountry = $this->exists('country_code');

        if ($hasPhone || $hasCountry) {
            $phone = $hasPhone ? $this->input('phone') : $this->user()->phone;
            $country = $hasCountry ? $this->input('country_code') : $this->user()->country_code;

            $data['country_code'] = $country
                ? strtoupper(trim(ltrim((string) $country, '+')))
                : null;
            $data['phone'] = $phone
                ? ltrim(preg_replace('/\D+/', '', $this->toEnglishDigits((string) $phone)), '0')
                : null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $country = $this->input('country_code') ?: $this->user()->country_code ?: 'AUTO';

        return [
            'name' => [
                'sometimes',
                Rule::requiredIf(! $this->user()->is_anonymous),
                'nullable',
                'string',
                'max:255',
            ],
            'display_name' => ['sometimes', 'required', 'string', 'max:80'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'phone' => ['sometimes', 'nullable', 'string', 'phone:'.$country],
            'date_of_birth' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(18)->toDateString(),
            ],
            'status' => ['sometimes', 'required', Rule::in(['student', 'worker', 'other'])],
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->filled('phone') || ! $this->filled('country_code')) {
            return;
        }

        $this->e164Phone = (new PhoneNumber(
            $this->input('phone'),
            $this->input('country_code')
        ))->formatE164();
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if ($this->e164Phone !== null) {
            $data['phone'] = $this->e164Phone;
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم الكامل مطلوب لهذا النوع من الحسابات.',
            'display_name.required' => 'الاسم الظاهر مطلوب.',
            'display_name.max' => 'يجب ألا يتجاوز الاسم الظاهر 80 محرفاً.',
            'country_code.size' => 'رمز الدولة يجب أن يكون بصيغة ISO-2 مثل SY.',
            'phone.phone' => 'رقم الهاتف المدخل غير صحيح مع رمز الدولة المختار.',
            'date_of_birth.before_or_equal' => 'يجب ألا يقل العمر عن 18 سنة.',
            'status.in' => 'الحالة يجب أن تكون طالباً أو عاملاً أو غير ذلك.',
        ];
    }

    private function toEnglishDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
