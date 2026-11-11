<?php

namespace App\Http\Requests\Api\Mood;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMoodLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'factor_ids' => $this->factor_ids ?? [],
        ]);
    }

    public function rules(): array
    {
        return [
            'mood_score' => 'required|integer|min:1|max:5',
            'factor_ids' => 'nullable|array|max:3',
            'factor_ids.*' => 'integer|distinct|exists:factors,id',
        ];
    }

    public function messages(): array
    {
        return [
            'mood_score.required' => 'يرجى اختيار حالتك المزاجية.',
            'mood_score.min' => 'قيمة المزاج غير صحيحة.',
            'mood_score.max' => 'قيمة المزاج غير صحيحة.',
            'factor_ids.max' => 'يمكنك اختيار ثلاثة عوامل كحد أقصى.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
