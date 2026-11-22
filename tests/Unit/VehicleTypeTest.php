<?php

namespace Tests\Unit;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit test aturan penempatan kendaraan (Blackbox: setiap kombinasi kendaraan × garasi), skenario C.4.
 */
class VehicleTypeTest extends TestCase
{
    #[Test]
    public function ut_29_sepeda_dan_motor_hanya_di_garasi_sepeda(): void
    {
        $this->assertSame([GarageType::Sepeda], VehicleType::Sepeda->allowedGarageTypes());
        $this->assertSame([GarageType::Sepeda], VehicleType::Motor->allowedGarageTypes());
        $this->assertFalse(VehicleType::Motor->canParkIn(GarageType::Mobil));
    }

    #[Test]
    public function ut_30_mobil_hanya_di_garasi_mobil(): void
    {
        $this->assertSame([GarageType::Mobil], VehicleType::Mobil->allowedGarageTypes());
        $this->assertFalse(VehicleType::Mobil->canParkIn(GarageType::MobilListrik));
    }

    #[Test]
    public function ut_31_mobil_listrik_di_garasi_mobil_atau_mobil_listrik(): void
    {
        $this->assertSame([GarageType::Mobil, GarageType::MobilListrik], VehicleType::MobilListrik->allowedGarageTypes());
        $this->assertFalse(VehicleType::MobilListrik->canParkIn(GarageType::Sepeda));
    }

    #[Test]
    public function ut_32_plat_wajib_kecuali_sepeda(): void
    {
        $this->assertFalse(VehicleType::Sepeda->requiresPlate());
        $this->assertTrue(VehicleType::Motor->requiresPlate());
        $this->assertTrue(VehicleType::Mobil->requiresPlate());
        $this->assertTrue(VehicleType::MobilListrik->requiresPlate());
    }
}
