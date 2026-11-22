<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Services\RentalPriceCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Unit test RentalPriceCalculator (Blackbox), skenario C.1–C.3 di 03_class_diagram.md.
 * Harga harian acuan Rp 20.000 (paket bulanan Rp 500.000).
 */
class RentalPriceCalculatorTest extends TestCase
{
    private const DAILY = 20000.0;

    private RentalPriceCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new RentalPriceCalculator;
    }

    /**
     * @return array<string, array{string, int, int, int, int, float, string}>
     *                                                                         [start, total_days, basis, months, days, biaya, end_date]
     */
    public static function newRentalCases(): array
    {
        return [
            'UT-01 BB 30 hari di bulan 31 hari = harian' => ['2026-12-01', 30, 31, 0, 30, 600000, '2026-12-30'],
            'UT-02 BB 31 hari di bulan 31 hari = 1 bulan' => ['2026-12-01', 31, 31, 1, 0, 500000, '2026-12-31'],
            'UT-03 BB 40 hari di bulan 31 hari = 1 bulan + 9 hari' => ['2026-12-01', 40, 31, 1, 9, 680000, '2027-01-09'],
            'UT-04 BB 62 hari = 2 bulan' => ['2026-12-01', 62, 31, 2, 0, 1000000, '2027-01-31'],
            'UT-05 BB 30 hari di bulan 30 hari = 1 bulan' => ['2026-11-01', 30, 30, 1, 0, 500000, '2026-11-30'],
            'UT-06 BB 31 hari di bulan 30 hari = 1 bulan + 1 hari' => ['2026-11-01', 31, 30, 1, 1, 520000, '2026-12-01'],
            'UT-07 BB ambang ikut bulan start_date' => ['2026-11-21', 30, 30, 1, 0, 500000, '2026-12-20'],
            'UT-08 BB Februari 28 hari = 1 bulan' => ['2027-02-01', 28, 28, 1, 0, 500000, '2027-02-28'],
            'UT-09 BB Februari 27 hari = harian' => ['2027-02-01', 27, 28, 0, 27, 540000, '2027-02-27'],
            'UT-10a BB Februari kabisat 28 hari = harian' => ['2028-02-01', 28, 29, 0, 28, 560000, '2028-02-28'],
            'UT-10b BB Februari kabisat 29 hari = 1 bulan' => ['2028-02-01', 29, 29, 1, 0, 500000, '2028-02-29'],
            'UT-11 BB durasi minimum 1 hari' => ['2026-12-01', 1, 31, 0, 1, 20000, '2026-12-01'],
        ];
    }

    #[Test]
    #[DataProvider('newRentalCases')]
    #[TestDox('Sewa baru: $_dataName')]
    public function kalkulasi_sewa_baru_bulanan_dan_harian(string $start, int $total, int $basis, int $months, int $days, float $cost, string $end): void
    {
        $startDate = CarbonImmutable::parse($start);

        $this->assertSame(['days_in_month' => $basis, 'months' => $months, 'days' => $days], $this->calc->breakdown($startDate, $total));
        $this->assertSame($cost, $this->calc->rentCost(self::DAILY, $startDate, $total));
        $this->assertSame($end, $this->calc->endDate($startDate, $total)->toDateString());
    }

    /** @return array<string, array{int}> */
    public static function invalidDurations(): array
    {
        return ['nol' => [0], 'negatif' => [-5]];
    }

    #[Test]
    #[DataProvider('invalidDurations')]
    public function ut_12_durasi_nol_atau_negatif_ditolak(int $total): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->rentCost(self::DAILY, CarbonImmutable::parse('2026-12-01'), $total);
    }

    #[Test]
    public function ut_14_perpanjangan_15_hari_setelah_sewa_25_hari_tetap_harian(): void
    {
        $start = CarbonImmutable::parse('2026-11-01');
        $initial = $this->calc->rentCost(self::DAILY, $start, 25);
        $end = $this->calc->endDate($start, 25);
        $extension = $this->calc->extensionCost(self::DAILY, $end, CarbonImmutable::parse('2026-12-10'));

        $this->assertSame('2026-11-25', $end->toDateString());
        $this->assertSame(500000.0, $initial);
        $this->assertSame(300000.0, $extension);
        $this->assertSame(800000.0, $initial + $extension);
        $this->assertNotSame(700000.0, $initial + $extension, 'Tidak boleh dikonversi menjadi 1 bulan + 10 hari');
    }

    #[Test]
    public function ut_15_perpanjangan_40_hari_tidak_memakai_tarif_bulanan(): void
    {
        $cost = $this->calc->extensionCost(self::DAILY, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2027-01-10'));

        $this->assertSame(800000.0, $cost);
        $this->assertNotSame(680000.0, $cost);
    }

    #[Test]
    public function ut_16_perpanjangan_minimal_1_hari(): void
    {
        $old = CarbonImmutable::parse('2026-11-25');
        $new = CarbonImmutable::parse('2026-11-26');

        $this->assertSame(1, $this->calc->extensionDays($old, $new));
        $this->assertSame(20000.0, $this->calc->extensionCost(self::DAILY, $old, $new));
    }

    /** @return array<string, array{string}> */
    public static function invalidExtensionDates(): array
    {
        return ['sama dengan tanggal lama' => ['2026-11-25'], 'sebelum tanggal lama' => ['2026-11-20']];
    }

    #[Test]
    #[DataProvider('invalidExtensionDates')]
    public function ut_17_tanggal_perpanjangan_tidak_setelah_tanggal_lama_ditolak(string $newEnd): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->extensionCost(self::DAILY, CarbonImmutable::parse('2026-11-25'), CarbonImmutable::parse($newEnd));
    }

    /**
     * @return array<string, array{VehicleType, GarageType, ?float, ?float, float}>
     */
    public static function chargingCases(): array
    {
        return [
            'UT-20 BB isi penuh di garasi E' => [VehicleType::MobilListrik, GarageType::MobilListrik, 10000, 4000, 15000000],
            'UT-21 BB baterai sudah penuh' => [VehicleType::MobilListrik, GarageType::MobilListrik, 10000, 10000, 0],
            'UT-22 BB baterai kosong' => [VehicleType::MobilListrik, GarageType::MobilListrik, 10000, 0, 25000000],
            'UT-23 BB mobil listrik di garasi C' => [VehicleType::MobilListrik, GarageType::Mobil, 10000, 4000, 0],
            'UT-24 BB kendaraan bukan listrik' => [VehicleType::Mobil, GarageType::Mobil, null, null, 0],
        ];
    }

    #[Test]
    #[DataProvider('chargingCases')]
    #[TestDox('Isi listrik: $_dataName')]
    public function kalkulasi_biaya_isi_listrik(VehicleType $vehicle, GarageType $garage, ?float $capacity, ?float $remaining, float $expected): void
    {
        $this->assertSame($expected, $this->calc->chargingCost($vehicle, $garage, $capacity, $remaining, 2500));
    }

    #[Test]
    public function ut_25_26_sisa_baterai_di_luar_rentang_ditolak_di_level_service(): void
    {
        foreach ([10001, -1] as $remaining) {
            try {
                $this->calc->chargingCost(VehicleType::MobilListrik, GarageType::MobilListrik, 10000, $remaining, 2500);
                $this->fail("Sisa baterai {$remaining} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
