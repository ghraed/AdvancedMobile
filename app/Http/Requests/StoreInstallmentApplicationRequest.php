<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreInstallmentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\-()]/', '', (string) $this->phone);
        if (str_starts_with($phone, '00961')) {
            $phone = '+961'.substr($phone, 4);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '+961'.substr($phone, 1);
        } elseif (preg_match('/^(3|70|71|76|78|79|81)/', $phone)) {
            $phone = '+961'.$phone;
        } $this->merge(['phone' => $phone]);
    }

    public function rules(): array
    {
        $file = ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('10mb')];

        return ['product_id' => ['required', 'integer', 'exists:products,id'], 'variant_id' => ['required', 'integer', 'exists:product_variants,id'], 'installment_months' => ['required', 'integer', 'in:3,6,9'], 'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'phone' => ['required', 'regex:/^\\+961(?:3|70|71|76|78|79|81)\\d{6}$/'], 'email' => ['required', 'email:rfc', 'max:255'], 'address' => ['required', 'string', 'max:2000'], 'identity_document_type' => ['required', 'in:lebanese_id,lebanese_passport,civil_registry_extract'], 'id_front' => $file, 'id_back' => [$this->input('identity_document_type') === 'lebanese_passport' ? 'nullable' : 'required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('10mb')], 'selfie_with_id' => $file, 'proof_of_address' => $file];
    }

    public function attributes(): array
    {
        return ['id_front' => 'identity document front', 'id_back' => 'identity document back', 'selfie_with_id' => 'selfie holding identity document', 'proof_of_address' => 'proof of address'];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Please enter a valid Lebanese phone number / يرجى إدخال رقم هاتف لبناني صحيح.', '*.required' => 'This field is required / هذا الحقل مطلوب.', '*.uploaded' => 'The upload failed. Maximum size is 10 MB / فشل الرفع. الحد الأقصى 10 ميغابايت.', '*.mimetypes' => 'Only JPG, JPEG, PNG, or PDF files are accepted / الملفات المسموحة JPG أو JPEG أو PNG أو PDF فقط.'];
    }
}
