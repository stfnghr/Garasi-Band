<?php

namespace Tests\Feature;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Enums\PaymentType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Blackbox test sewa baru lewat HTTP: skenario UT-12, UT-13, UT-25..28, UT-32..34.
 * Garasi C: harian Rp 20.000. Garasi E: harian Rp 30.000, Rp 2.500/kWh.
 */
class RentalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->user = User::factory()->create();
        $garages = app(GarageService::class);
        $garages->createBatch(GarageType::Sepeda, 2, 5000);
        $garages->createBatch(GarageType::Mobil, 2, 20000);
        $garages->createBatch(GarageType::MobilListrik, 2, 30000, 2500);
    }

    /** Data form sewa mobil listrik di garasi E001, 3 hari, kapasitas 10.000 kWh, sisa 4.000 kWh. */
    private function evData(array $overrides = []): array
    {
        $data = [
            'vehicle_type' => 'mobil_listrik',
            'plate_number' => 'L 1234 AB',
            'brand_model' => 'Hyundai Ioniq 5',
            'battery_capacity_kwh' => 10000,
            'remaining_battery_kwh' => 4000,
            'total_days' => 3,
            'garage_type' => 'mobil_listrik',
            'garage_id' => $this->garageId('E001'),
            'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ...$overrides,
        ];

        // Tipe sewa mengikuti durasi bila tidak diisi: >= 31 hari (Desember) = bulanan, selain itu harian.
        return $data + ['rental_type' => (int) $data['total_days'] >= 31 ? 'monthly' : 'daily'];
    }

    private function carData(array $overrides = []): array
    {
        return $this->evData([
            'vehicle_type' => 'mobil', 'plate_number' => 'B 1 XX', 'brand_model' => 'Toyota Avanza',
            'battery_capacity_kwh' => null, 'remaining_battery_kwh' => null,
            'garage_type' => 'mobil', 'garage_id' => $this->garageId('C001'),
            ...$overrides,
        ]);
    }

    private function garageId(string $code): int
    {
        return Garage::where('code', $code)->value('id');
    }

    private function rent(array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user)->post(route('user.rentals.store'), $data);
    }

    #[Test]
    public function ut_70_sewa_harian_maksimal_jumlah_hari_bulan_dikurangi_satu(): void
    {
        // Desember 31 hari: harian maksimal 30 hari.
        $this->rent($this->carData(['rental_type' => 'daily', 'total_days' => 30]))->assertSessionHasNoErrors();
        $this->rent($this->carData(['rental_type' => 'daily', 'total_days' => 31, 'garage_id' => $this->garageId('C002')]))
            ->assertSessionHasErrors('total_days');
    }

    #[Test]
    public function ut_71_sewa_bulanan_minimal_sama_dengan_jumlah_hari_bulan(): void
    {
        // Desember 31 hari: bulanan minimal 31 hari.
        $this->rent($this->carData(['rental_type' => 'monthly', 'total_days' => 30]))->assertSessionHasErrors('total_days');
        $this->rent($this->carData(['rental_type' => 'monthly', 'total_days' => 31, 'garage_id' => $this->garageId('C002')]))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function ut_12_durasi_nol_ditolak_validasi(): void
    {
        $this->rent($this->carData(['total_days' => 0]))->assertSessionHasErrors('total_days');
        $this->rent($this->carData(['total_days' => -5]))->assertSessionHasErrors('total_days');
        $this->assertSame(0, Rental::count());
    }

    #[Test]
    public function ut_13_rincian_bulanan_dan_harian_tersimpan_di_database(): void
    {
        $this->rent($this->carData(['total_days' => 40]))
            ->assertRedirect(route('user.rentals.index'))->assertSessionHasNoErrors();

        $rental = Rental::with('payments', 'garage')->sole();
        $this->assertSame('2026-12-01', $rental->start_date->toDateString());
        $this->assertSame('2027-01-09', $rental->end_date->toDateString());
        $this->assertSame(31, $rental->days_in_month);
        $this->assertSame(1, $rental->billed_months);
        $this->assertSame(9, $rental->billed_days);
        $this->assertEquals(680000, $rental->rent_cost);
        $this->assertEquals(0, $rental->charging_cost);
        $this->assertEquals(680000, $rental->total_cost);
        $this->assertSame('1 month(s) + 9 day(s)', $rental->billingLabel());
        $this->assertSame(GarageStatus::Used, $rental->garage->status);

        $payment = $rental->payments->sole();
        $this->assertSame(PaymentType::SewaBaru, $payment->type);
        $this->assertEquals(680000, $payment->amount);
        Storage::disk('public')->assertExists($payment->proof_path);
    }

    #[Test]
    public function ut_25_sisa_baterai_melebihi_kapasitas_ditolak(): void
    {
        $this->rent($this->evData(['remaining_battery_kwh' => 10001]))->assertSessionHasErrors('remaining_battery_kwh');
        $this->assertSame(0, Rental::count());
    }

    #[Test]
    public function ut_26_sisa_baterai_negatif_ditolak(): void
    {
        $this->rent($this->evData(['remaining_battery_kwh' => -1]))->assertSessionHasErrors('remaining_battery_kwh');
    }

    #[Test]
    public function ut_27_mobil_listrik_wajib_isi_kapasitas_dan_sisa_baterai(): void
    {
        $this->rent($this->evData(['remaining_battery_kwh' => '']))->assertSessionHasErrors('remaining_battery_kwh');
        $this->rent($this->evData(['battery_capacity_kwh' => '']))->assertSessionHasErrors('battery_capacity_kwh');
        $this->assertSame(0, Rental::count());
    }

    #[Test]
    public function ut_28_total_sewa_baru_adalah_sewa_ditambah_isi_listrik(): void
    {
        $this->rent($this->evData())->assertSessionHasNoErrors();

        $rental = Rental::with('vehicle')->sole();
        $this->assertEquals(90000, $rental->rent_cost);           // 3 x 30.000
        $this->assertEquals(15000000, $rental->charging_cost);    // (10.000 - 4.000) x 2.500
        $this->assertEquals(15090000, $rental->total_cost);
        $this->assertEquals(10000, $rental->vehicle->battery_capacity_kwh);
        $this->assertEquals(4000, $rental->remaining_battery_kwh);
        $this->assertSame('2026-12-03', $rental->end_date->toDateString());
    }

    #[Test]
    public function ut_23_blackbox_mobil_listrik_di_garasi_mobil_tanpa_biaya_listrik(): void
    {
        $this->rent($this->evData(['garage_type' => 'mobil', 'garage_id' => $this->garageId('C001')]))->assertSessionHasNoErrors();

        $rental = Rental::sole();
        $this->assertEquals(0, $rental->charging_cost);
        $this->assertEquals(60000, $rental->total_cost);
    }

    #[Test]
    public function ut_32_sepeda_tanpa_plat_lolos_motor_tanpa_plat_ditolak(): void
    {
        $bike = ['vehicle_type' => 'sepeda', 'plate_number' => null, 'brand_model' => 'Polygon', 'garage_type' => 'sepeda', 'garage_id' => $this->garageId('S001')];
        $this->rent($this->carData($bike))->assertSessionHasNoErrors();

        $motor = [...$bike, 'vehicle_type' => 'motor', 'garage_id' => $this->garageId('S002')];
        $this->rent($this->carData($motor))->assertSessionHasErrors('plate_number');

        $this->assertSame(1, Rental::count());
    }

    #[Test]
    public function ut_33_penempatan_kendaraan_yang_salah_ditolak(): void
    {
        $this->rent($this->carData(['garage_type' => 'mobil_listrik', 'garage_id' => $this->garageId('E001')]))
            ->assertSessionHasErrors('garage_type');
        $this->rent($this->carData(['vehicle_type' => 'motor', 'garage_type' => 'mobil']))
            ->assertSessionHasErrors('garage_type');

        $this->assertSame(0, Rental::count());
    }

    #[Test]
    public function ut_34_garasi_terpakai_tidak_tampil_dan_tidak_bisa_disewa_ganda(): void
    {
        $this->rent($this->evData())->assertSessionHasNoErrors();

        $this->assertSame(['E002'], app(GarageService::class)->availableToday(GarageType::MobilListrik)->pluck('code')->all());
        $this->rent($this->evData(['plate_number' => 'N 9 ZZ']), User::factory()->create())->assertSessionHasErrors('garage_id');
        $this->assertSame(1, Rental::count());
    }

    #[Test]
    public function ut_66_user_boleh_menyewa_lebih_dari_satu_garasi(): void
    {
        $this->rent($this->evData())->assertSessionHasNoErrors();
        $this->rent($this->carData())->assertSessionHasNoErrors();

        $this->assertSame(2, $this->user->rentals()->count());
    }

    #[Test]
    public function ut_67_kendaraan_berplat_dipakai_ulang_sedangkan_sepeda_selalu_record_baru(): void
    {
        $this->rent($this->carData(['plate_number' => 'b 1 xx']));
        Carbon::setTestNow('2026-12-05 10:00:00');
        $this->rent($this->carData(['garage_id' => $this->garageId('C002')]));

        $bike = ['vehicle_type' => 'sepeda', 'plate_number' => null, 'brand_model' => 'Polygon', 'garage_type' => 'sepeda'];
        $this->rent($this->carData([...$bike, 'garage_id' => $this->garageId('S001')]));
        $this->rent($this->carData([...$bike, 'garage_id' => $this->garageId('S002')]));

        $this->assertSame(1, Vehicle::where('plate_number', 'B 1 XX')->count());
        $this->assertSame(2, Vehicle::where('type', 'sepeda')->count());
    }

    #[Test]
    public function ut_68_halaman_cari_garasi_menampilkan_rincian_dan_total_biaya(): void
    {
        $query = collect($this->carData(['total_days' => 40]))->except(['garage_id', 'payment_proof'])->all();

        $this->actingAs($this->user)->get(route('user.rentals.create', $query))
            ->assertOk()->assertSee('C001')->assertSee('C002')->assertDontSee('E001')
            ->assertSee('Rp 680.000')->assertSee('09-01-2027')->assertSee('1 month(s) + 9 day(s)');
    }

    #[Test]
    public function ut_69_status_garasi_kembali_available_setelah_end_date_inklusif_lewat(): void
    {
        $this->rent($this->evData())->assertSessionHasNoErrors();   // end 2026-12-03

        Carbon::setTestNow('2026-12-03 23:00:00');
        app(GarageService::class)->syncStatuses();
        $this->assertSame(GarageStatus::Used, Garage::where('code', 'E001')->value('status'));

        Carbon::setTestNow('2026-12-04 00:01:00');
        app(GarageService::class)->syncStatuses();
        $this->assertSame(GarageStatus::Available, Garage::where('code', 'E001')->value('status'));
    }
}
