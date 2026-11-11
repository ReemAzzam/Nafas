<?php

namespace App\Http\Requests\Api\Content;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CompleteActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_activity_id' => [
                'nullable',
                'integer',
                'exists:plan_activities,id',
                'prohibits:mood_log_id',
            ],
            'mood_log_id' => [
                'nullable',
                'integer',
                'exists:mood_logs,id',
                'prohibits:plan_activity_id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_activity_id.prohibits' => 'لا يمكن ربط الإنجاز بنشاط خطة واقتراح مزاج في الوقت نفسه.',
            'mood_log_id.prohibits' => 'لا يمكن ربط الإنجاز باقتراح مزاج ونشاط خطة في الوقت نفسه.',
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
