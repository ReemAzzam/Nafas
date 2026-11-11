<?php

namespace App\Http\Requests\Api\Journal;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = is_string($this->body)
            ? trim(strip_tags($this->body))
            : $this->body;

        $prompt = is_string($this->prompt)
            ? trim(strip_tags($this->prompt))
            : $this->prompt;

        $this->merge([
            'body' => $body,
            'prompt' => $prompt === '' ? null : $prompt,
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => 'required|string|min:2|max:10000',
            'prompt' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'اكتب ما يدور في بالك قبل الحفظ.',
            'body.string' => 'يجب أن تكون اليوميات نصًا.',
            'body.min' => 'اكتب حرفين على الأقل قبل الحفظ.',
            'body.max' => 'يجب ألا يزيد النص عن 10000 حرف.',
            'prompt.string' => 'يجب أن يكون السؤال نصًا.',
            'prompt.max' => 'يجب ألا يزيد السؤال عن 200 حرف.',
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
