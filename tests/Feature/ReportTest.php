<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\User;
use App\Services\GarageService;
use App\Services\RentalService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        app(GarageService::class)->createBatch(GarageType::Sepeda, 2, 5000);
        app(GarageService::class)->createBatch(GarageType::Mobil, 1, 20000);
        $user = User::factory()->create(['name' => 'Budi']);
        $rentals = app(RentalService::class);
        $sepeda = ['vehicle_type' => 'sepeda', 'plate_number' => null, 'brand_model' => 'Polygon', 'total_days' => 2, 'garage_type' => 'sepeda'];

        Carbon::setTestNow('2026-10-05 09:00');
        $rentals->createRental($user, [...$sepeda, 'garage_id' => Garage::where('code', 'S001')->value('id')], UploadedFile::fake()->image('a.jpg'));
        Carbon::setTestNow('2026-11-21 09:00');
        $rentals->createRental($user, [...$sepeda, 'garage_id' => Garage::where('code', 'S002')->value('id')], UploadedFile::fake()->image('b.jpg'));
        $rentals->createRental(User::factory()->create(['name' => 'Sari']), [
            'vehicle_type' => 'mobil', 'plate_number' => 'B 1 XX', 'brand_model' => 'Avanza', 'total_days' => 30,
            'garage_type' => 'mobil', 'garage_id' => Garage::where('code', 'C001')->value('id'),
        ], UploadedFile::fake()->image('c.jpg'));
    }

    #[Test]
    public function ut_56_laporan_per_bulan_dalam_rentang_tahun(): void
    {
        $report = app(ReportService::class)->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $rows = $report['monthly'];

        $this->assertCount(12, $rows);
        $this->assertEquals(10000, $rows[9]['total']);            // Oktober
        $this->assertEquals(10000 + 500000, $rows[10]['total']);  // November
        $this->assertSame(2, $rows[10]['sewa_baru']);
        $this->assertEquals(520000, $report['summary']['total']);
    }

    #[Test]
    public function ut_57_laporan_per_hari_dalam_bulan(): void
    {
        $rows = app(ReportService::class)->build(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'))['daily'];

        $this->assertCount(30, $rows);
        $this->assertEquals(510000, $rows[20]['total']);  // 21 November
        $this->assertEquals(0, $rows[0]['total']);
    }

    #[Test]
    public function ut_58_laporan_per_tipe_garasi_dan_per_user(): void
    {
        $report = app(ReportService::class)->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $byType = $report['by_type']->keyBy('key');
        $this->assertEquals(20000, $byType['sepeda']['total']);
        $this->assertEquals(500000, $byType['mobil']['total']);
        $this->assertEquals(0, $byType['mobil_listrik']['total']);

        $this->assertStringStartsWith('Sari', $report['by_user'][0]['label']);
        $this->assertEquals(20000, $report['by_user'][1]['total']);
        $this->assertSame('C001', $report['by_garage'][0]['label']);
    }

    #[Test]
    public function ut_59_filter_tipe_garasi_hanya_menghitung_tipe_tersebut(): void
    {
        $report = app(ReportService::class)->build(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), GarageType::Sepeda);

        $this->assertEquals(20000, $report['summary']['total']);
        $this->assertCount(1, $report['by_type']);
        $this->assertCount(2, $report['details']);
    }

    #[Test]
    public function ut_60_halaman_laporan_dapat_dibuka_dengan_berbagai_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertSee('Rental Reports');
        $this->actingAs($admin)->get('/admin/reports?from=2026-01-01&to=2026-12-31&type=mobil')
            ->assertOk()->assertSee('C001')->assertDontSee('S001');
        $this->actingAs($admin)->get('/admin/reports?from=2026-12-31&to=2026-01-01')->assertSessionHasErrors('to');
    }
}
