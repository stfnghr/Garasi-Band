<?php

namespace App\Enums;

/** Tipe garasi beserta prefix kode ID-nya (S/C/E). */
enum GarageType: string
{
    case Sepeda = 'sepeda';
    case Mobil = 'mobil';
    case MobilListrik = 'mobil_listrik';

    /** Prefix kode garasi, contoh: S001, C010, E005. */
    public function prefix(): string
    {
        return match ($this) {
            self::Sepeda => 'S',
            self::Mobil => 'C',
            self::MobilListrik => 'E',
        };
    }

    /** Kunci warna tampilan (class CSS .type-*) agar tiap tipe mudah dibedakan di tabel & grafik. */
    public function color(): string
    {
        return match ($this) {
            self::Sepeda => 'sepeda',
            self::Mobil => 'mobil',
            self::MobilListrik => 'listrik',
        };
    }

    /** Warna heksadesimal untuk grafik Chart.js (selaras dengan CSS). */
    public function hex(): string
    {
        return match ($this) {
            self::Sepeda => '#0f9d74',
            self::Mobil => '#c2416b',
            self::MobilListrik => '#e8930c',
        };
    }

    /** Nama ikon Bootstrap Icons. */
    public function icon(): string
    {
        return match ($this) {
            self::Sepeda => 'bi-bicycle',
            self::Mobil => 'bi-car-front',
            self::MobilListrik => 'bi-ev-front',
        };
    }

    /** Label pendek untuk tombol filter. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Sepeda => 'Bike & Motorcycle',
            self::Mobil => 'Car',
            self::MobilListrik => 'Electric Car',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sepeda => 'Bike Garage',
            self::Mobil => 'Car Garage',
            self::MobilListrik => 'EV Garage',
        };
    }
}
