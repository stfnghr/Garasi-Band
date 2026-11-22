<?php

namespace Database\Seeders;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Data awal untuk demo.
 *
 * Akun (password semua: "password"):
 * - admin@garasi.test  : Admin
 * - budi@garasi.test   : User disetujui (punya contoh sewa)
 * - sari@garasi.test   : User disetujui (punya contoh sewa mobil listrik)
 * - citra@garasi.test  : Pendaftar menunggu verifikasi
 */
class DatabaseSeeder extends Seeder
{
    public function run(GarageService $garages, RentalService $rentals): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administrator', 'email' => 'admin@garasi.test']);
        $budi = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@garasi.test', 'nik' => '3578010101900001']);
        $sari = User::factory()->create(['name' => 'Sari Wulandari', 'email' => 'sari@garasi.test', 'nik' => '3578014505920002']);
        User::factory()->pending()->create(['name' => 'Citra Lestari', 'email' => 'citra@garasi.test', 'nik' => '3578016607950003']);

        $garages->createBatch(GarageType::Sepeda, 10, 5000);
        $garages->createBatch(GarageType::Mobil, 10, 20000);
        $garages->createBatch(GarageType::MobilListrik, 5, 30000, 2500);

        $garageId = fn (string $code) => Garage::where('code', $code)->value('id');
        $proof = fn () => UploadedFile::fake()->image('bukti.jpg', 300, 200);

        // Sewa bulan lalu yang sudah selesai (untuk data laporan).
        Carbon::setTestNow(now()->subMonthNoOverflow()->startOfMonth()->addDays(4));
        $rentals->createRental($budi, [
            'vehicle_type' => 'motor', 'plate_number' => 'L 4321 XY', 'brand_model' => 'Honda Beat',
            'total_days' => 7, 'garage_type' => 'sepeda', 'garage_id' => $garageId('S001'),
        ], $proof());
        Carbon::setTestNow();

        // Sewa aktif hari ini: 40 hari (paket bulanan + sisa harian) lalu diperpanjang 5 hari.
        $rental = $rentals->createRental($budi, [
            'vehicle_type' => 'mobil', 'plate_number' => 'L 1234 AB', 'brand_model' => 'Toyota Avanza',
            'total_days' => 40, 'garage_type' => 'mobil', 'garage_id' => $garageId('C001'),
        ], $proof());
        $rentals->extendRental($rental, $rental->end_date->addDays(5), $proof(), $admin);

        // Mobil listrik di garasi E: isi penuh (kapasitas - sisa) x harga per kWh.
        $rentals->createRental($sari, [
            'vehicle_type' => 'mobil_listrik', 'plate_number' => 'W 88 EV', 'brand_model' => 'Hyundai Ioniq 5',
            'battery_capacity_kwh' => 72.6, 'remaining_battery_kwh' => 20, 'total_days' => 3,
            'garage_type' => 'mobil_listrik', 'garage_id' => $garageId('E001'),
        ], $proof());

        // Garasi dari sewa yang sudah selesai kembali 'available' (normalnya dijalankan scheduler harian).
        $garages->syncStatuses();
    }
}
