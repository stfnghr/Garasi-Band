<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Models\Garage;
use Illuminate\Validation\ValidationException;

/**
 * Pembuat ID garasi: prefix tipe (S/C/E) + 3 digit incremental per tipe, maksimal 999.
 */
class GarageCodeGenerator
{
    public const MAX_SEQUENCE = 999;

    /** Contoh: format(Mobil, 10) => "C010". */
    public function format(GarageType $type, int $sequence): string
    {
        return $type->prefix().str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor urut berikutnya setelah $last sebanyak $count.
     *
     * @return list<int>
     *
     * @throws ValidationException bila melewati 999
     */
    public function sequencesAfter(int $last, int $count): array
    {
        if ($count < 1) {
            throw ValidationException::withMessages(['count' => 'Number of garages must be at least 1.']);
        }

        if ($last + $count > self::MAX_SEQUENCE) {
            $remaining = self::MAX_SEQUENCE - $last;
            throw ValidationException::withMessages([
                'count' => "Only {$remaining} garage ID(s) left for this type (maximum ".self::MAX_SEQUENCE.').',
            ]);
        }

        return range($last + 1, $last + $count);
    }

    /** Nomor urut terakhir yang sudah dipakai untuk tipe garasi ini (0 bila belum ada). */
    public function lastSequence(GarageType $type): int
    {
        return (int) Garage::where('type', $type)->lockForUpdate()->max('sequence');
    }
}
