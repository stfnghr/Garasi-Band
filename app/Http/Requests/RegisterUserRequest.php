<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi pendaftaran user baru (UC01).
 *
 * Aturan revisi:
 * - NIK hanya angka, tepat 16 digit.
 * - Nomor telepon hanya angka (tanda "-" dari tampilan dibuang), 10–15 digit, diawali 08.
 * - Nama & email maksimal 50 karakter; email wajib punya domain lengkap (contoh: @gmail.com).
 * - Email/NIK yang pernah DITOLAK admin tidak dapat didaftarkan kembali.
 */
class RegisterUserRequest extends FormRequest
{
    /** Panjang maksimal nama dan email. */
    public const MAX_NAME = 50;

    public const MAX_EMAIL = 50;

    /** Pola email dengan domain lengkap, misal nama@gmail.com (menolak nama@gmail). */
    public const EMAIL_REGEX = '/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rapikan input sebelum divalidasi:
     * - telepon "0812-3456-7890" menjadi "081234567890" (tanda "-" hanya untuk tampilan),
     * - email dijadikan huruf kecil & tanpa spasi, nama tanpa spasi di ujung.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nik' => ['bail', 'required', 'digits:16', $this->notRejected('NIK'), 'unique:users,nik'],
            'name' => ['required', 'string', 'max:'.self::MAX_NAME],
            'phone' => ['required', 'regex:/^08[0-9]{8,13}$/'],
            'email' => ['bail', 'required', 'max:'.self::MAX_EMAIL, 'email:filter', 'regex:'.self::EMAIL_REGEX, $this->notRejected('email'), 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Rule: tolak nilai (email/NIK) milik pendaftar yang sudah DITOLAK admin,
     * dengan pesan khusus (dijalankan sebelum rule unique berkat "bail").
     */
    private function notRejected(string $label): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($label) {
            if (User::where($attribute, $value)->where('status', UserStatus::Rejected)->exists()) {
                $fail("This {$label} was rejected by the admin and cannot be registered again.");
            }
        };
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone number must be 10–15 digits and start with 08.',
            'email.regex' => 'Email must include a full domain, e.g. name@gmail.com.',
            'email.email' => 'Email must include a full domain, e.g. name@gmail.com.',
        ];
    }

    public function attributes(): array
    {
        return ['nik' => 'NIK', 'name' => 'full name', 'phone' => 'phone number', 'email' => 'email', 'password' => 'password'];
    }
}
