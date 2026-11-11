<?php

namespace App\Http\Requests\Api\Audio;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ListAudioTracksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.string' => 'تصنيف المقطع غير صالح.',
            'category.max' => 'تصنيف المقطع طويل أكثر من اللازم.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'البيانات المدخلة غير صالحة.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
