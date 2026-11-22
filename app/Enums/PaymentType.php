<?php

namespace App\Enums;

/** Jenis pembayaran: pelunasan sewa baru atau perpanjangan. */
enum PaymentType: string
{
    case SewaBaru = 'sewa_baru';
    case Perpanjangan = 'perpanjangan';

    public function label(): string
    {
        return match ($this) {
            self::SewaBaru => 'New Rental',
            self::Perpanjangan => 'Extension',
        };
    }
}
