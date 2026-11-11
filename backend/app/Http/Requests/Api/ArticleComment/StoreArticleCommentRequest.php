<?php

namespace App\Http\Requests\Api\ArticleComment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreArticleCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->body)) {
            $body = preg_replace('/\s+/u', ' ', strip_tags(trim($this->body)));
            $this->merge(['body' => $body]);
        }
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|min:2|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'يرجى كتابة التعليق.',
            'body.string' => 'يجب أن يكون التعليق نصًا.',
            'body.min' => 'يجب ألا يقل التعليق عن حرفين.',
            'body.max' => 'يجب ألا يزيد التعليق عن 500 حرف.',
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
