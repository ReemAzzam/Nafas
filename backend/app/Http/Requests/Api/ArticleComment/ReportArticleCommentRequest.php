<?php

namespace App\Http\Requests\Api\ArticleComment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportArticleCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->reason)) {
            $this->merge([
                'reason' => preg_replace('/\s+/u', ' ', strip_tags(trim($this->reason))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.string' => 'يجب أن يكون سبب البلاغ نصًا.',
            'reason.max' => 'يجب ألا يزيد سبب البلاغ عن 300 حرف.',
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
