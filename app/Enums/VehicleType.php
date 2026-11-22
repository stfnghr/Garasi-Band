<?php

namespace App\Enums;

/** Tipe kendaraan user dan aturan penempatannya di garasi. */
enum VehicleType: string
{
    case Sepeda = 'sepeda';
    case Motor = 'motor';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    /**
     * Tipe garasi yang boleh ditempati kendaraan ini.
     *
     * @return list<GarageType>
     */
    public function allowedGarageTypes(): array
    {
        return match ($this) {
            self::Sepeda, self::Motor => [GarageType::Sepeda],
            self::Mobil => [GarageType::Mobil],
            self::MobilListrik => [GarageType::Mobil, GarageType::MobilListrik],
        };
    }

    public function canParkIn(GarageType $garageType): bool
    {
        return in_array($garageType, $this->allowedGarageTypes(), true);
    }

    /** Nomor plat wajib untuk semua kendaraan kecuali sepeda. */
    public function requiresPlate(): bool
    {
        return $this !== self::Sepeda;
    }

    public function isElectric(): bool
    {
        return $this === self::MobilListrik;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Bicycle',
            self::Motor => 'Motorcycle',
            self::Mobil => 'Car',
            self::MobilListrik => 'Electric Car',
        };
    }
}
