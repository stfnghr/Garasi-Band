<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Kalkulasi tarif sewa garasi (BR-01 s.d. BR-06).
 *
 * Class ini murni (tanpa akses database) agar mudah diuji dengan unit test.
 */
class RentalPriceCalculator
{
    /** Harga paket bulanan = 25 x harga harian. */
    public const MONTHLY_MULTIPLIER = 25;

    /**
     * Ambang bulanan (BR-01): jumlah hari pada bulan tanggal mulai sewa (28..31).
     */
    public function daysInMonthOf(CarbonInterface $start): int
    {
        return $start->daysInMonth;
    }

    /**
     * Pecah durasi sewa baru menjadi paket bulanan + sisa harian (BR-03, BR-04).
     *
     * @param  CarbonInterface  $start  tanggal mulai sewa
     * @param  int  $totalDays  total durasi sewa (minimal 1 hari)
     * @return array{days_in_month: int, months: int, days: int}
     *
     * @throws InvalidArgumentException bila durasi kurang dari 1 hari
     */
    public function breakdown(CarbonInterface $start, int $totalDays): array
    {
        $this->assertPositiveDays($totalDays);
        $basis = $this->daysInMonthOf($start);

        return [
            'days_in_month' => $basis,
            'months' => intdiv($totalDays, $basis),
            'days' => $totalDays % $basis,
        ];
    }

    /**
     * Biaya sewa baru = jumlah_bulan x (25 x harian) + sisa_hari x harian.
     *
     * @param  float  $dailyPrice  harga sewa harian garasi
     * @param  CarbonInterface  $start  tanggal mulai sewa
     * @param  int  $totalDays  total durasi sewa
     * @return float biaya sewa dalam rupiah
     */
    public function rentCost(float $dailyPrice, CarbonInterface $start, int $totalDays): float
    {
        $b = $this->breakdown($start, $totalDays);

        return round($b['months'] * self::MONTHLY_MULTIPLIER * $dailyPrice + $b['days'] * $dailyPrice, 2);
    }

    /**
     * Tanggal selesai inklusif (BR-02) = start + (total_days - 1) hari.
     *
     * @throws InvalidArgumentException bila durasi kurang dari 1 hari
     */
    public function endDate(CarbonInterface $start, int $totalDays): CarbonImmutable
    {
        $this->assertPositiveDays($totalDays);

        return CarbonImmutable::instance($start)->startOfDay()->addDays($totalDays - 1);
    }

    /**
     * Biaya isi listrik hingga penuh (BR-06).
     * Hanya untuk mobil listrik di garasi mobil listrik: (kapasitas - sisa) x harga per kWh.
     *
     * @param  float|null  $capacityKwh  kapasitas baterai penuh kendaraan
     * @param  float|null  $remainingKwh  sisa baterai saat masuk
     * @param  float|null  $kwhPrice  harga isi listrik per kWh garasi
     *
     * @throws InvalidArgumentException bila sisa baterai di luar 0..kapasitas
     */
    public function chargingCost(VehicleType $vehicle, GarageType $garage, ?float $capacityKwh, ?float $remainingKwh, ?float $kwhPrice): float
    {
        if (! $vehicle->isElectric() || $garage !== GarageType::MobilListrik) {
            return 0.0;
        }

        $capacity = (float) $capacityKwh;
        $remaining = (float) $remainingKwh;
        if ($remaining < 0 || $remaining > $capacity) {
            throw new InvalidArgumentException('Remaining battery must be between 0 and the battery capacity.');
        }

        return round(($capacity - $remaining) * (float) $kwhPrice, 2);
    }

    /**
     * Jumlah tambahan hari perpanjangan = selisih tanggal selesai baru dan lama.
     *
     * @throws InvalidArgumentException bila tanggal baru tidak setelah tanggal lama
     */
    public function extensionDays(CarbonInterface $currentEnd, CarbonInterface $newEnd): int
    {
        $days = (int) CarbonImmutable::instance($currentEnd)->startOfDay()
            ->diffInDays(CarbonImmutable::instance($newEnd)->startOfDay(), false);

        if ($days < 1) {
            throw new InvalidArgumentException('The new end date must be after the current end date.');
        }

        return $days;
    }

    /**
     * Sisa biaya perpanjangan (BR-05): SELALU tarif harian, tidak pernah dikonversi ke paket bulanan.
     */
    public function extensionCost(float $dailyPrice, CarbonInterface $currentEnd, CarbonInterface $newEnd): float
    {
        return round($this->extensionDays($currentEnd, $newEnd) * $dailyPrice, 2);
    }

    private function assertPositiveDays(int $totalDays): void
    {
        if ($totalDays < 1) {
            throw new InvalidArgumentException('Rental duration must be at least 1 day.');
        }
    }
}
