<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Enums\PaymentType;
use App\Models\Garage;
use App\Models\Rental;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Blackbox test perpanjangan sewa (selalu harian): skenario UT-14, UT-17, UT-18, UT-19.
 * Sewa awal: mulai 2026-11-01 (November 30 hari), 25 hari, garasi C001 harian Rp 20.000 => selesai 2026-11-25.
 */
class RentalExtensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Rental $rental;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow('2026-11-01 08:00:00');
        $this->admin = User::factory()->admin()->create();
        app(GarageService::class)->createBatch(GarageType::Mobil, 1, 20000);

        $this->rental = app(RentalService::class)->createRental(User::factory()->create(), [
            'vehicle_type' => 'mobil', 'plate_number' => 'B 1 XX', 'brand_model' => 'Toyota Avanza',
            'total_days' => 25, 'garage_type' => 'mobil', 'garage_id' => Garage::value('id'),
        ], UploadedFile::fake()->image('bukti.jpg'));

        Carbon::setTestNow('2026-11-20 09:00:00');
    }

    private function extend(string $newEnd)
    {
        return $this->actingAs($this->admin)->put(route('admin.extensions.update', $this->rental), [
            'new_end_date' => $newEnd,
            'payment_proof' => UploadedFile::fake()->image('perpanjang.jpg'),
        ]);
    }

    #[Test]
    public function ut_14_sewa_25_hari_diperpanjang_15_hari_tetap_harian(): void
    {
        $this->assertEquals(500000, $this->rental->rent_cost);
        $this->assertSame('2026-11-25', $this->rental->end_date->toDateString());

        $this->actingAs($this->admin)->get(route('admin.extensions.edit', [$this->rental, 'new_end_date' => '2026-12-10']))
            ->assertOk()->assertSeeText('Extra 15 day(s)')->assertSee('Rp 300.000');

        $this->extend('2026-12-10')->assertSessionHasNoErrors();

        $this->assertEquals(800000, $this->rental->fresh()->total_cost);   // bukan 700.000 (1 bulan + 10 hari)
    }

    #[Test]
    public function ut_17_tanggal_baru_sama_atau_sebelum_tanggal_lama_ditolak(): void
    {
        $this->extend('2026-11-25')->assertSessionHasErrors('new_end_date');
        $this->extend('2026-11-20')->assertSessionHasErrors('new_end_date');

        $this->assertCount(1, $this->rental->payments()->get());
    }

    #[Test]
    public function ut_18_multi_bukti_bayar_tersimpan_dan_rincian_awal_tidak_berubah(): void
    {
        $this->extend('2026-12-10')->assertRedirect(route('admin.extensions.index'))->assertSessionHasNoErrors();

        $rental = $this->rental->fresh();
        $this->assertSame('2026-12-10', $rental->end_date->toDateString());
        $this->assertEquals(800000, $rental->total_cost);
        $this->assertSame(0, $rental->billed_months);
        $this->assertSame(25, $rental->billed_days);
        $this->assertEquals(500000, $rental->rent_cost);

        $this->assertSame(2, $rental->payments()->count());
        $extension = $rental->payments()->where('type', PaymentType::Perpanjangan)->sole();
        $this->assertSame(15, $extension->extra_days);
        $this->assertEquals(300000, $extension->amount);
        $this->assertSame('2026-11-25', $extension->previous_end_date->toDateString());
        $this->assertSame('2026-12-10', $extension->new_end_date->toDateString());
        $this->assertSame($this->admin->id, $extension->uploaded_by);
        Storage::disk('public')->assertExists($extension->proof_path);
    }

    #[Test]
    public function ut_19_daftar_perpanjangan_hanya_sewa_aktif_hari_ini_end_date_inklusif(): void
    {
        Carbon::setTestNow('2026-11-25 09:00:00');
        $this->actingAs($this->admin)->get(route('admin.extensions.index'))->assertSee('C001');
        $this->assertCount(0, app(GarageService::class)->availableToday(GarageType::Mobil));

        Carbon::setTestNow('2026-11-26 09:00:00');
        $this->actingAs($this->admin)->get(route('admin.extensions.index'))->assertDontSee('C001');
        $this->assertSame(['C001'], app(GarageService::class)->availableToday(GarageType::Mobil)->pluck('code')->all());
        $this->actingAs($this->admin)->get(route('admin.extensions.edit', $this->rental))->assertNotFound();
    }
}
