<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\PaymentType;
use App\Enums\VehicleType;
use App\Models\Garage;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Logika bisnis sewa garasi baru dan perpanjangan sewa.
 */
class RentalService
{
    public function __construct(private readonly RentalPriceCalculator $calculator) {}

    /**
     * Hitung rincian biaya sewa baru untuk satu garasi, dimulai hari ini.
     *
     * @param  array{vehicle_type: string, total_days: int|string, battery_capacity_kwh?: float|string|null, remaining_battery_kwh?: float|string|null}  $criteria
     * @return array{start_date: CarbonImmutable, end_date: CarbonImmutable, days_in_month: int, months: int, days: int, rent_cost: float, charging_cost: float, total_cost: float}
     */
    public function quote(array $criteria, Garage $garage): array
    {
        $start = today()->toImmutable();
        $totalDays = (int) $criteria['total_days'];
        $breakdown = $this->calculator->breakdown($start, $totalDays);

        $rentCost = $this->calculator->rentCost((float) $garage->daily_price, $start, $totalDays);
        $chargingCost = $this->calculator->chargingCost(
            VehicleType::from($criteria['vehicle_type']),
            $garage->type,
            isset($criteria['battery_capacity_kwh']) ? (float) $criteria['battery_capacity_kwh'] : null,
            isset($criteria['remaining_battery_kwh']) ? (float) $criteria['remaining_battery_kwh'] : null,
            $garage->kwh_price !== null ? (float) $garage->kwh_price : null,
        );

        return [
            'start_date' => $start,
            'end_date' => $this->calculator->endDate($start, $totalDays),
            ...$breakdown,
            'rent_cost' => $rentCost,
            'charging_cost' => $chargingCost,
            'total_cost' => round($rentCost + $chargingCost, 2),
        ];
    }

    /**
     * Simpan sewa baru beserta bukti pelunasan, lalu tandai garasi 'used'.
     *
     * @param  User  $user  user yang sudah disetujui
     * @param  array<string, mixed>  $data  data tervalidasi dari StoreRentalRequest
     * @param  UploadedFile  $proof  gambar bukti pelunasan
     *
     * @throws ValidationException bila garasi tidak cocok/sudah terpakai atau data kendaraan bertentangan
     */
    public function createRental(User $user, array $data, UploadedFile $proof): Rental
    {
        return DB::transaction(function () use ($user, $data, $proof) {
            /** @var Garage $garage */
            $garage = Garage::lockForUpdate()->findOrFail($data['garage_id']);
            $vehicleType = VehicleType::from($data['vehicle_type']);

            if (! $vehicleType->canParkIn($garage->type)) {
                throw ValidationException::withMessages(['garage_id' => "A {$vehicleType->label()} cannot be parked in a {$garage->type->label()}."]);
            }
            if ($garage->rentals()->activeOn(today())->exists()) {
                throw ValidationException::withMessages(['garage_id' => "Garage {$garage->code} is already rented, please choose another one."]);
            }

            $vehicle = $this->resolveVehicle($user, $vehicleType, $data);
            // Biaya listrik dihitung dari kapasitas yang tersimpan di data kendaraan.
            $criteria = [...$data, 'battery_capacity_kwh' => $vehicle->battery_capacity_kwh];
            try {
                $quote = $this->quote($criteria, $garage);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['remaining_battery_kwh' => $e->getMessage()]);
            }

            $rental = Rental::create([
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
                'garage_id' => $garage->id,
                'start_date' => $quote['start_date'],
                'end_date' => $quote['end_date'],
                'total_days' => (int) $data['total_days'],
                'days_in_month' => $quote['days_in_month'],
                'billed_months' => $quote['months'],
                'billed_days' => $quote['days'],
                'daily_price' => $garage->daily_price,
                'kwh_price' => $garage->kwh_price,
                'remaining_battery_kwh' => $vehicleType->isElectric() ? $data['remaining_battery_kwh'] : null,
                'rent_cost' => $quote['rent_cost'],
                'charging_cost' => $quote['charging_cost'],
                'total_cost' => $quote['total_cost'],
            ]);

            $rental->payments()->create([
                'uploaded_by' => $user->id,
                'type' => PaymentType::SewaBaru,
                'amount' => $quote['total_cost'],
                'proof_path' => $proof->store('bukti-bayar', 'public'),
                'paid_at' => now(),
            ]);

            $garage->update(['status' => GarageStatus::Used]);

            return $rental;
        });
    }

    /**
     * Ambil atau buat data kendaraan user.
     * Kendaraan berplat dicari berdasarkan user_id + plate_number (firstOrCreate);
     * sepeda tanpa plat selalu dibuat sebagai record baru.
     *
     * @throws ValidationException bila plat sudah terdaftar dengan tipe kendaraan lain
     */
    private function resolveVehicle(User $user, VehicleType $type, array $data): Vehicle
    {
        $plate = isset($data['plate_number']) ? strtoupper(trim($data['plate_number'])) : null;
        $attributes = [
            'type' => $type,
            'brand_model' => $data['brand_model'],
            'battery_capacity_kwh' => $type->isElectric() ? $data['battery_capacity_kwh'] : null,
        ];

        if ($plate === null) {
            return $user->vehicles()->create($attributes);
        }

        $vehicle = $user->vehicles()->firstOrCreate(['plate_number' => $plate], $attributes);
        if ($vehicle->type !== $type) {
            throw ValidationException::withMessages(['plate_number' => "Plate {$plate} is already registered as a {$vehicle->type->label()}."]);
        }

        return $vehicle;
    }

    /**
     * Sisa biaya bila tanggal selesai sewa diubah ke $newEndDate (selalu harian).
     *
     * @throws InvalidArgumentException bila tanggal baru tidak setelah tanggal selesai lama
     */
    public function extensionCost(Rental $rental, CarbonInterface $newEndDate): float
    {
        return $this->calculator->extensionCost((float) $rental->daily_price, $rental->end_date, $newEndDate);
    }

    /**
     * Perpanjang sewa: ubah tanggal selesai, tambah total biaya, dan catat bukti bayar baru.
     * Rincian billed_months/billed_days sewa awal tidak diubah (BR-05).
     *
     * @param  Rental  $rental  sewa yang aktif hari ini
     * @param  CarbonInterface  $newEndDate  tanggal selesai baru (inklusif)
     * @param  UploadedFile  $proof  gambar bukti bayar perpanjangan
     * @param  User  $admin  admin yang memproses
     * @return Payment record pembayaran perpanjangan
     */
    public function extendRental(Rental $rental, CarbonInterface $newEndDate, UploadedFile $proof, User $admin): Payment
    {
        return DB::transaction(function () use ($rental, $newEndDate, $proof, $admin) {
            $rental = Rental::lockForUpdate()->findOrFail($rental->id);
            $previousEnd = $rental->end_date;
            $extraDays = $this->calculator->extensionDays($previousEnd, $newEndDate);
            $amount = $this->extensionCost($rental, $newEndDate);

            $rental->update([
                'end_date' => $newEndDate,
                'total_cost' => round((float) $rental->total_cost + $amount, 2),
            ]);

            return $rental->payments()->create([
                'uploaded_by' => $admin->id,
                'type' => PaymentType::Perpanjangan,
                'amount' => $amount,
                'extra_days' => $extraDays,
                'previous_end_date' => $previousEnd,
                'new_end_date' => $newEndDate,
                'proof_path' => $proof->store('bukti-bayar', 'public'),
                'paid_at' => now(),
            ]);
        });
    }

    /**
     * Daftar sewa yang sedang berjalan hari ini (untuk fitur perpanjangan).
     *
     * @return Collection<int, Rental>
     */
    public function activeToday(): Collection
    {
        return Rental::activeOn(today())->with(['garage', 'user', 'vehicle'])->orderBy('end_date')->get();
    }
}
