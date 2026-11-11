<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'blocked', 'deleted'])],
            'role' => ['nullable', Rule::in(['user', 'admin'])],
            'onboarding_status' => ['nullable', Rule::in(['pending', 'goals_set', 'assessed', 'plan_ready', 'skipped'])],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registered_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'حالة الحساب المطلوبة غير صالحة.',
            'role.in' => 'الدور المطلوب غير صالح.',
            'onboarding_status.in' => 'حالة التهيئة المطلوبة غير صالحة.',
            'registered_from.date_format' => 'تاريخ البداية يجب أن يكون بالصيغة YYYY-MM-DD.',
            'registered_to.date_format' => 'تاريخ النهاية يجب أن يكون بالصيغة YYYY-MM-DD.',
            'registered_to.after_or_equal' => 'تاريخ النهاية يجب ألا يسبق تاريخ البداية.',
            'per_page.max' => 'الحد الأقصى للصفحة هو 100 سجل.',
        ];
    }
}
