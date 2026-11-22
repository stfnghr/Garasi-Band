<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi ubah profil: hanya nama dan nomor telepon (email & NIK tidak bisa diubah).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Buang tanda "-" dari nomor telepon tampilan. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => $this->filled('phone') ? preg_replace('/\D/', '', (string) $this->input('phone')) : null,
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.RegisterUserRequest::MAX_NAME],
            // Admin boleh tanpa telepon; user wajib.
            'phone' => [$this->user()->isAdmin() ? 'nullable' : 'required', 'regex:/^08[0-9]{8,13}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Phone number must be 10–15 digits and start with 08.'];
    }

    public function attributes(): array
    {
        return ['name' => 'full name', 'phone' => 'phone number'];
    }
}
