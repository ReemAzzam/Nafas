<?php

namespace App\Http\Requests\Api\Sos;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpsertSosContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->phone;
        if (is_string($phone)) {
            $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $phone = str_replace($eastern, $western, $phone);
        }

        $this->merge(['phone' => $phone]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:80',
            'phone' => 'required|string|phone:SY,mobile',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى إدخال الاسم.',
            'name.string' => 'يجب أن يكون الاسم نصًا.',
            'name.min' => 'يجب ألا يقل الاسم عن حرفين.',
            'name.max' => 'يجب ألا يزيد الاسم عن 80 حرفًا.',
            'phone.required' => 'يرجى إدخال رقم الموبايل.',
            'phone.string' => 'يجب أن يكون رقم الموبايل نصًا.',
            'phone.phone' => 'يجب أن يكون الرقم موبايل سوري صالح.',
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
