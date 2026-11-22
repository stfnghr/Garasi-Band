<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Enums\UserStatus;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test fitur revisi "notes MC": validasi registrasi, profil & hapus akun,
 * kelola garasi massal (ubah harga / hapus / detail), dan sewa via tanggal selesai.
 * Diberi kode UT-42..UT-55 (semua blackbox) (lanjutan UT-01..UT-41 di dokumen 03).
 */
class RevisionFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow('2026-11-21 09:00');
    }

    private function registrationData(array $overrides = []): array
    {
        return [
            'nik' => '3578010101900001', 'name' => 'Andi User', 'phone' => '0812-3456-7890',
            'email' => 'andi@example.com', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            ...$overrides,
        ];
    }

    /** Sewa satu garasi sepeda untuk $user mulai hari ini selama $days hari. */
    private function rentBike(User $user, int $days = 3, string $code = 'S001'): Rental
    {
        return app(RentalService::class)->createRental($user, [
            'vehicle_type' => 'sepeda', 'plate_number' => null, 'brand_model' => 'Polygon',
            'total_days' => $days, 'garage_type' => 'sepeda', 'garage_id' => Garage::where('code', $code)->value('id'),
        ], UploadedFile::fake()->image('bukti.jpg'));
    }

    // ===== Registrasi =====

    #[Test]
    public function ut_42_telepon_dengan_tanda_strip_diterima_dan_disimpan_sebagai_angka(): void
    {
        $this->post('/register', $this->registrationData())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'andi@example.com', 'phone' => '081234567890']);
    }

    public static function invalidRegistrations(): array
    {
        return [
            'telepon > 15 digit' => [['phone' => '0812-3456-7890-1234'], 'phone'],
            'telepon < 10 digit' => [['phone' => '0812-345'], 'phone'],
            'NIK berisi huruf' => [['nik' => '35780101019000AB'], 'nik'],
            'nama > 50 karakter' => [['name' => str_repeat('a', 51)], 'name'],
            'email > 50 karakter' => [['email' => str_repeat('a', 41).'@gmail.com'], 'email'],
            'email tanpa .com (@gmail)' => [['email' => 'andi@gmail'], 'email'],
            'nama kosong' => [['name' => ''], 'name'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRegistrations')]
    public function ut_43_registrasi_tidak_valid_ditolak(array $override, string $field): void
    {
        $this->post('/register', $this->registrationData($override))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function ut_44_email_dan_nik_yang_pernah_ditolak_tidak_bisa_daftar_lagi(): void
    {
        User::factory()->create(['email' => 'andi@example.com', 'nik' => '3578010101900001', 'status' => UserStatus::Rejected]);

        $this->post('/register', $this->registrationData())
            ->assertSessionHasErrors(['email' => 'This email was rejected by the admin and cannot be registered again.'])
            ->assertSessionHasErrors('nik');
    }

    // ===== Profil & hapus akun =====

    #[Test]
    public function ut_45_ubah_profil_tanpa_bisa_mengubah_email(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Budi Baru', 'phone' => '0811-2222-3333', 'email' => 'lain@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Budi Baru', 'phone' => '081122223333', 'email' => 'budi@example.com']);
    }

    #[Test]
    public function ut_46_ganti_password_butuh_password_lama_yang_benar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', ['current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/profile/password', ['current_password' => 'password', 'password' => 'baru12345', 'password_confirmation' => 'baru12345'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('baru12345', $user->fresh()->password));
    }

    #[Test]
    public function ut_47_hapus_akun_ditolak_bila_masih_ada_sewa_aktif(): void
    {
        app(GarageService::class)->createBatch(GarageType::Sepeda, 1, 5000);
        $user = User::factory()->create();
        $this->rentBike($user);

        $this->actingAs($user)->delete('/profile', ['delete_password' => 'password'])->assertSessionHasErrors('account');
        $this->assertNotSoftDeleted($user);
    }

    #[Test]
    public function ut_48_akun_dihapus_bisa_daftar_ulang_dengan_email_sama_tanpa_data_lama(): void
    {
        app(GarageService::class)->createBatch(GarageType::Sepeda, 1, 5000);
        $user = User::factory()->create(['email' => 'andi@example.com', 'nik' => '3578010101900001']);
        Carbon::setTestNow('2026-10-01 09:00');
        $this->rentBike($user, 2); // sewa lama sudah selesai
        Carbon::setTestNow('2026-11-21 09:00');

        $this->actingAs($user)->delete('/profile', ['delete_password' => 'password'])->assertRedirect(route('login'));
        $this->assertSoftDeleted($user);
        $this->assertGuest();

        $this->post('/register', $this->registrationData())->assertSessionHasNoErrors();
        $fresh = User::where('email', 'andi@example.com')->firstOrFail();
        $this->assertNotSame($user->id, $fresh->id);
        $this->assertSame(0, $fresh->rentals()->count());
        $this->assertSame(1, Rental::count()); // transaksi lama tetap ada untuk laporan
    }

    // ===== Kelola garasi =====

    #[Test]
    public function ut_49_ubah_harga_massal_hanya_satu_tipe_dan_tidak_mengubah_snapshot(): void
    {
        $service = app(GarageService::class);
        $service->createBatch(GarageType::Sepeda, 2, 5000);
        $service->createBatch(GarageType::Mobil, 1, 20000);
        $rental = $this->rentBike(User::factory()->create());
        $admin = User::factory()->admin()->create();
        $ids = fn (string ...$codes) => Garage::whereIn('code', $codes)->pluck('id')->all();

        $this->actingAs($admin)->patch('/admin/garages/prices', ['ids' => $ids('S001', 'C001'), 'daily_price' => 'Rp 7.000'])
            ->assertSessionHasErrors();
        $this->actingAs($admin)->patch('/admin/garages/prices', ['ids' => $ids('S001', 'S002'), 'daily_price' => 'Rp 7.000'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(7000, Garage::where('code', 'S002')->value('daily_price'));
        $this->assertEquals(5000, $rental->fresh()->daily_price);
    }

    #[Test]
    public function ut_50_ubah_harga_garasi_listrik_wajib_harga_kwh(): void
    {
        app(GarageService::class)->createBatch(GarageType::MobilListrik, 1, 30000, 2500);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/garages/prices', ['ids' => [Garage::value('id')], 'daily_price' => '35000'])
            ->assertSessionHasErrors();
        $this->actingAs($admin)->patch('/admin/garages/prices', ['ids' => [Garage::value('id')], 'daily_price' => '35000', 'kwh_price' => '3000'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(3000, Garage::value('kwh_price'));
    }

    #[Test]
    public function ut_51_detail_garasi_menampilkan_user_saat_ini_dan_riwayat(): void
    {
        app(GarageService::class)->createBatch(GarageType::Sepeda, 1, 5000);
        Carbon::setTestNow('2026-10-01 09:00');
        $this->rentBike(User::factory()->create(['name' => 'User Lama']), 2);
        Carbon::setTestNow('2026-11-21 09:00');
        $this->rentBike(User::factory()->create(['name' => 'User Kini']));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/garages/'.Garage::value('id'))
            ->assertOk()->assertSee('User Kini')->assertSee('User Lama');
    }

    #[Test]
    public function ut_52_daftar_garasi_paginasi_dan_ringkasan_mengikuti_filter(): void
    {
        $service = app(GarageService::class);
        $service->createBatch(GarageType::Sepeda, 30, 5000);
        $service->createBatch(GarageType::Mobil, 5, 20000);
        $admin = User::factory()->admin()->create();

        $page = $this->actingAs($admin)->get('/admin/garages?type=mobil&per_page=25')->assertOk();
        $this->assertSame(5, $page->viewData('summary')['total']);
        $this->assertSame(25, $page->viewData('garages')->perPage());

        $all = $this->actingAs($admin)->get('/admin/garages?per_page=999')->assertOk();
        $this->assertSame(35, $all->viewData('summary')['total']);
        $this->assertSame(10, $all->viewData('garages')->perPage()); // nilai di luar 10/25/50 kembali ke 10
    }

    // ===== Sewa baru: user mengisi durasi, tanggal selesai dihitung otomatis =====

    #[Test]
    public function ut_53_durasi_diisi_user_dan_tanggal_selesai_dihitung_otomatis(): void
    {
        app(GarageService::class)->createBatch(GarageType::Sepeda, 1, 5000);
        $user = User::factory()->create();

        // Hari ini 21 Nov + 5 hari inklusif = selesai 25 Nov; motor otomatis ke garasi S.
        // end_date yang dikirim klien (2030) diabaikan; server menghitung sendiri.
        $this->actingAs($user)->get('/user/rentals/new?'.http_build_query([
            'vehicle_type' => 'motor', 'plate_number' => 'L 1 AB', 'brand_model' => 'Beat',
            'rental_type' => 'daily', 'total_days' => 5, 'end_date' => '2030-01-01',
        ]))->assertOk()->assertSee('S001')->assertSee('Rp 25.000')->assertSee('25-11-2026');

        $this->actingAs($user)->get('/user/rentals/new?'.http_build_query([
            'vehicle_type' => 'motor', 'plate_number' => 'L 1 AB', 'brand_model' => 'Beat', 'rental_type' => 'daily', 'total_days' => 0,
        ]))->assertSessionHasErrors('total_days');
    }

    #[Test]
    public function ut_54_sisa_baterai_tidak_boleh_negatif_atau_melebihi_kapasitas(): void
    {
        app(GarageService::class)->createBatch(GarageType::MobilListrik, 1, 30000, 2500);
        $user = User::factory()->create();
        $query = fn (string $remaining) => '/user/rentals/new?'.http_build_query([
            'vehicle_type' => 'mobil_listrik', 'plate_number' => 'W 1 EV', 'brand_model' => 'Ioniq', 'rental_type' => 'daily', 'total_days' => 3,
            'battery_capacity_kwh' => '60', 'remaining_battery_kwh' => $remaining, 'garage_type' => 'mobil_listrik',
        ]);

        $this->actingAs($user)->get($query('-1'))->assertSessionHasErrors('remaining_battery_kwh');
        $this->actingAs($user)->get($query('61'))->assertSessionHasErrors('remaining_battery_kwh');
        $this->actingAs($user)->get($query('1e2'))->assertSessionHasErrors('remaining_battery_kwh');
        $this->actingAs($user)->get($query('20'))->assertOk()->assertSee('E001');
    }

    #[Test]
    public function ut_55_riwayat_user_hanya_menampilkan_sewa_miliknya(): void
    {
        app(GarageService::class)->createBatch(GarageType::Sepeda, 2, 5000);
        $mine = User::factory()->create();
        $this->rentBike($mine, 3, 'S001');
        $this->rentBike(User::factory()->create(), 3, 'S002');

        $page = $this->actingAs($mine)->get('/user/history')->assertOk()->assertSee('S001')->assertDontSee('S002');
        $this->assertSame(1, $page->viewData('stats')['total']);
    }
}
