<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi sewa garasi baru oleh user (UC03).
 */
class StoreRentalRequest extends FormRequest
{
    /** Batas teknis durasi (10 tahun) agar input ekstrem tidak masuk; spesifikasi hanya mensyaratkan minimal 1 hari. */
    public const MAX_TOTAL_DAYS = 3650;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi input form sewa (dipakai oleh langkah "Cari Garasi" dan saat menyimpan):
     * 1. User mengisi total_days (durasi, hari). Tanggal mulai = hari ini, dan end_date (inklusif)
     *    dihitung server = hari ini + (total_days - 1). Input end_date dari klien selalu diabaikan.
     * 2. Kendaraan yang hanya punya SATU tipe garasi (sepeda/motor => S, mobil => C)
     *    otomatis diberi garage_type tersebut; hanya mobil listrik yang memilih C atau E.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $days = filter_var($input['total_days'] ?? null, FILTER_VALIDATE_INT);
        if ($days !== false && $days >= 1) {
            $input['end_date'] = today()->addDays($days - 1)->toDateString();
        }

        $vehicle = VehicleType::tryFrom((string) ($input['vehicle_type'] ?? ''));
        if ($vehicle && empty($input['garage_type']) && count($vehicle->allowedGarageTypes()) === 1) {
            $input['garage_type'] = $vehicle->allowedGarageTypes()[0]->value;
        }

        return $input;
    }

    /** Terapkan normalize() sebelum validasi. */
    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    /**
     * Aturan kriteria pencarian garasi (dipakai juga oleh langkah "Cari Garasi").
     * Field yang tidak relevan dengan tipe kendaraan dibuang (exclude) dari data tervalidasi.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function criteriaRules(): array
    {
        $listrik = VehicleType::MobilListrik->value;

        return [
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'plate_number' => ['exclude_if:vehicle_type,'.VehicleType::Sepeda->value, 'required', 'string', 'max:15', 'regex:/^[A-Za-z0-9 ]+$/'],
            'brand_model' => ['required', 'string', 'max:100'],
            'battery_capacity_kwh' => ['exclude_unless:vehicle_type,'.$listrik, 'required', 'numeric', 'gt:0', 'max:99999999'],
            'remaining_battery_kwh' => ['exclude_unless:vehicle_type,'.$listrik, 'required', 'numeric', 'min:0', 'lte:battery_capacity_kwh'],
            'end_date' => ['nullable', 'date', 'after_or_equal:today'],
            'rental_type' => ['required', Rule::in(['daily', 'monthly'])],
            'total_days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TOTAL_DAYS],
            'garage_type' => ['required', Rule::enum(GarageType::class)],
        ];
    }

    /** Validasi silang (BR-08): tipe garasi harus sesuai tipe kendaraan. */
    public static function checkGarageType(Validator $validator): void
    {
        $vehicle = VehicleType::tryFrom((string) $validator->getValue('vehicle_type'));
        $garage = GarageType::tryFrom((string) $validator->getValue('garage_type'));

        if ($vehicle && $garage && ! $vehicle->canParkIn($garage)) {
            $validator->errors()->add('garage_type', "A {$vehicle->label()} cannot be parked in a {$garage->label()}.");
        }
    }

    /**
     * Validasi silang tipe sewa (BR-09): harian maksimal (jumlah hari bulan ini - 1),
     * bulanan minimal sama dengan jumlah hari bulan ini. Ambang memakai bulan tanggal mulai (hari ini).
     */
    public static function checkRentalType(Validator $validator): void
    {
        $type = $validator->getValue('rental_type');
        $days = filter_var($validator->getValue('total_days'), FILTER_VALIDATE_INT);
        if ($days === false || ! in_array($type, ['daily', 'monthly'], true)) {
            return; // input dasar yang salah sudah dilaporkan oleh aturan masing-masing field
        }

        $month = today()->daysInMonth;
        if ($type === 'daily' && $days > $month - 1) {
            $validator->errors()->add('total_days', 'Daily rental can be at most '.($month - 1)." day(s) this month. Choose monthly for {$month} days or more.");
        }
        if ($type === 'monthly' && $days < $month) {
            $validator->errors()->add('total_days', "Monthly rental needs at least {$month} day(s) this month. Choose daily for fewer days.");
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...self::criteriaRules(),
            'garage_id' => ['required', 'integer', Rule::exists('garages', 'id')->where('type', $this->input('garage_type'))],
            'payment_proof' => ['required', 'image', 'max:2048'],
        ];
    }

    /** Validasi silang (tipe garasi & tipe sewa) dipasang langsung pada validator. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => self::checkGarageType($v));
        $validator->after(fn (Validator $v) => self::checkRentalType($v));
    }

    public function attributes(): array
    {
        return self::attributeNames();
    }

    /** Nama field dalam Bahasa Indonesia untuk pesan validasi. */
    public static function attributeNames(): array
    {
        return [
            'vehicle_type' => 'vehicle type',
            'plate_number' => 'plate number',
            'brand_model' => 'brand and model',
            'battery_capacity_kwh' => 'full battery capacity',
            'remaining_battery_kwh' => 'remaining battery',
            'end_date' => 'rental end date',
            'rental_type' => 'rental type',
            'total_days' => 'rental duration',
            'garage_type' => 'garage type',
            'garage_id' => 'garage',
            'payment_proof' => 'payment proof',
        ];
    }
}
