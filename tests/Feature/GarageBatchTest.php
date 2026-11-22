<?php

namespace Tests\Feature;

use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\User;
use App\Services\GarageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Blackbox test pembuatan garasi berkelompok: skenario UT-36..UT-41.
 */
class GarageBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function store(array $data)
    {
        return $this->actingAs($this->admin)->post(route('admin.garages.store'), $data);
    }

    private function codes(string $type): array
    {
        return Garage::where('type', $type)->orderBy('sequence')->pluck('code')->all();
    }

    #[Test]
    public function ut_36_id_incremental_per_tipe(): void
    {
        $this->store(['type' => 'sepeda', 'count' => 3, 'daily_price' => 5000])->assertSessionHasNoErrors();
        $this->store(['type' => 'sepeda', 'count' => 2, 'daily_price' => 6000])->assertSessionHasNoErrors();
        $this->store(['type' => 'mobil', 'count' => 10, 'daily_price' => 20000])->assertSessionHasNoErrors();

        $this->assertSame(['S001', 'S002', 'S003', 'S004', 'S005'], $this->codes('sepeda'));
        $this->assertSame('C001', $this->codes('mobil')[0]);
        $this->assertSame('C010', $this->codes('mobil')[9]);
    }

    #[Test]
    public function ut_37_tepat_999_garasi_dari_kosong(): void
    {
        $this->store(['type' => 'mobil_listrik', 'count' => 999, 'daily_price' => 30000, 'kwh_price' => 2500])->assertSessionHasNoErrors();

        $this->assertSame(999, Garage::count());
        $this->assertSame('E999', Garage::orderByDesc('sequence')->value('code'));
    }

    #[Test]
    public function ut_38_jumlah_1000_ditolak(): void
    {
        $this->store(['type' => 'mobil', 'count' => 1000, 'daily_price' => 20000])->assertSessionHasErrors('count');
        $this->assertSame(0, Garage::count());
    }

    #[Test]
    public function ut_39_sisa_kuota_id(): void
    {
        app(GarageService::class)->createBatch(GarageType::Mobil, 998, 20000);

        $this->store(['type' => 'mobil', 'count' => 2, 'daily_price' => 20000])
            ->assertSessionHasErrors(['count' => 'Only 1 garage ID(s) left for this type (maximum 999).']);
        $this->store(['type' => 'mobil', 'count' => 1, 'daily_price' => 20000])->assertSessionHasNoErrors();

        $this->assertSame(999, Garage::count());
        $this->assertSame('C999', Garage::orderByDesc('sequence')->value('code'));
    }

    #[Test]
    public function ut_40_jumlah_nol_ditolak(): void
    {
        $this->store(['type' => 'sepeda', 'count' => 0, 'daily_price' => 5000])->assertSessionHasErrors('count');
    }

    #[Test]
    public function ut_41_garasi_mobil_listrik_wajib_harga_per_kwh(): void
    {
        $this->store(['type' => 'mobil_listrik', 'count' => 1, 'daily_price' => 30000])->assertSessionHasErrors('kwh_price');

        $this->store(['type' => 'mobil_listrik', 'count' => 1, 'daily_price' => 30000, 'kwh_price' => 2500])->assertSessionHasNoErrors();
        $this->assertEquals(2500, Garage::sole()->kwh_price);
    }
}
