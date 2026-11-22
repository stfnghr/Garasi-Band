<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Services\GarageCodeGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pembuatan garasi berkelompok oleh admin (UC05).
 */
class StoreGarageBatchRequest extends FormRequest
{
    /** Batas harga (sesuai kolom decimal(12,2)). */
    public const MAX_PRICE = 9999999999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ambil digit saja dari input berformat rupiah, contoh "Rp 200.000" => "200000".
     * Mengembalikan null bila kosong.
     */
    public static function digitsOnly(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    /** Bersihkan format rupiah dan jumlah sebelum divalidasi. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'daily_price' => self::digitsOnly($this->input('daily_price')),
            'kwh_price' => self::digitsOnly($this->input('kwh_price')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(GarageType::class)],
            'count' => ['required', 'integer', 'min:1', 'max:'.GarageCodeGenerator::MAX_SEQUENCE],
            'daily_price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE],
            'kwh_price' => ['nullable', 'required_if:type,'.GarageType::MobilListrik->value, 'integer', 'min:1', 'max:'.self::MAX_PRICE],
        ];
    }

    public function messages(): array
    {
        return ['kwh_price.required_if' => 'Charging price per kWh is required for EV garages.'];
    }

    public function attributes(): array
    {
        return [
            'type' => 'garage type',
            'count' => 'number of garages',
            'daily_price' => 'daily price',
            'kwh_price' => 'charging price per kWh',
        ];
    }
}
