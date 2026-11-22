<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi ubah harga garasi secara massal (admin memilih beberapa garasi satu tipe).
 */
class UpdateGaragePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Input harga tampil sebagai "Rp 20.000"; ambil angkanya saja. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'daily_price' => StoreGarageBatchRequest::digitsOnly($this->input('daily_price')),
            'kwh_price' => StoreGarageBatchRequest::digitsOnly($this->input('kwh_price')),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:garages,id'],
            'daily_price' => ['required', 'integer', 'min:1', 'max:'.StoreGarageBatchRequest::MAX_PRICE],
            'kwh_price' => ['nullable', 'integer', 'min:1', 'max:'.StoreGarageBatchRequest::MAX_PRICE],
        ];
    }

    public function messages(): array
    {
        return ['ids.required' => 'Select at least one garage first.'];
    }

    public function attributes(): array
    {
        return ['daily_price' => 'daily price', 'kwh_price' => 'charging price per kWh', 'ids' => 'garages'];
    }
}
