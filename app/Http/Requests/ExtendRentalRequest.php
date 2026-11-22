<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi perpanjangan sewa oleh admin. */
class ExtendRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_end_date' => ['required', 'date', 'after:'.$this->route('rental')->end_date->toDateString()],
            'payment_proof' => ['required', 'image', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return ['new_end_date' => 'new end date', 'payment_proof' => 'payment proof'];
    }
}
