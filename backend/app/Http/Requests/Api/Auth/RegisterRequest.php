<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\PhoneNumber;

class RegisterRequest extends FormRequest
{
    private ?string $e164Phone = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * تجهيز وتنظيف المدخلات قبل التحقق (Sanitizing Input)
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->convertArabicToEnglishNumbers($this->phone);

        $this->merge([
            'is_anonymous' => filter_var($this->is_anonymous, FILTER_VALIDATE_BOOLEAN),
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'country_code' => $this->country_code ? strtoupper(trim(ltrim($this->country_code, '+'))) : null,
            'phone' => $phone ? ltrim(preg_replace('/\D+/', '', $phone), '0') : null,
        ]);
    }

    /**
     * دالة مساعدة لتحويل الأرقام العربية/الشرقية إلى إنجليزية
     */
    private function convertArabicToEnglishNumbers(?string $string): ?string
    {
        if (! $string) {
            return null;
        }

        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicDigits, $englishDigits, $string);
    }

    public function rules(): array
    {
        return [
            'is_anonymous' => 'required|boolean',
            'name' => 'required_if:is_anonymous,false|nullable|string|max:255',
            'display_name' => 'required|string|max:80',
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],

            // شروط كلمة السر القوية: حرف كبير + حرف صغير + رقم + رمز خاص + 8 أحرف على الأقل
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()  // حرف كبير وحرف صغير
                    ->numbers()    // أرقام
                    ->symbols(),   // رموز مثل @#$%
            ],

            'country_code' => 'required_with:phone|nullable|string|size:2',
            'phone' => 'nullable|string|phone:'.($this->country_code ?? 'AUTO'),
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(18)->toDateString(),
            ],
            'status' => 'required|in:student,worker,other',
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->phone || ! $this->country_code) {
            return;
        }

        $this->e164Phone = (new PhoneNumber($this->phone, $this->country_code))->formatE164();
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        if ($this->e164Phone !== null) {
            $data['phone'] = $this->e164Phone;
        }

        return $key === null ? $data : data_get($data, $key, $default);
    }

    public function messages(): array
    {
        return [
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique' => 'البريد الإلكتروني مُسجل مسبقاً، يمكنك تسجيل الدخول.',

            'password.required' => 'كلمة السر مطلوبة.',
            'password.confirmed' => 'تأكيد كلمة السر غير متطابق.',

            'phone.phone' => 'رقم الهاتف المدخل غير صحيح مع رمز الدولة المختار.',
            'country_code.size' => 'رمز الدولة يجب أن يكون مكوناً من حرفين رمزية (مثل SY أو SA).',
        ];
    }

    /**
     * إرجاع JSON موحد عند فشل الـ Validation بدلاً من Redirect
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422));
    }
}
