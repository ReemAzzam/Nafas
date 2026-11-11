<?php

namespace App\Http\Requests\Api\Feedback;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $message = is_string($this->message)
            ? trim(strip_tags($this->message))
            : $this->message;

        $this->merge([
            'message' => $message === '' ? null : $message,
        ]);
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|between:1,5',
            'message' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'يرجى اختيار تقييم من نجمة إلى خمس نجوم.',
            'rating.integer' => 'يجب أن يكون التقييم رقمًا صحيحًا.',
            'rating.between' => 'يجب أن يكون التقييم بين نجمة وخمس نجوم.',
            'message.string' => 'يجب أن تكون الملاحظة نصًا.',
            'message.max' => 'يجب ألا تزيد الملاحظة عن 2000 حرف.',
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
