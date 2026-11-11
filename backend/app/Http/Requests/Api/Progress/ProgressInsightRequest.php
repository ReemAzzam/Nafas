<?php

namespace App\Http\Requests\Api\Progress;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ProgressInsightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => $this->input('period', 'week'),
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['week', 'month'])],
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'period.required' => 'يرجى تحديد الفترة.',
            'period.in' => 'الفترة يجب أن تكون أسبوعًا أو شهرًا.',
            'date.date_format' => 'يجب أن يكون التاريخ بالصيغة YYYY-MM-DD.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
