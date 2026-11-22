<?php

namespace App\Models;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit garasi yang disewakan. Kode: prefix tipe + 3 digit urut.
 */
#[Fillable(['code', 'type', 'sequence', 'daily_price', 'kwh_price', 'status'])]
class Garage extends Model
{
    protected function casts(): array
    {
        return [
            'type' => GarageType::class,
            'status' => GarageStatus::class,
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
        ];
    }

    /** Riwayat sewa garasi ini. */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** Garasi yang tidak sedang disewa pada tanggal tertentu. */
    public function scopeAvailableOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereDoesntHave('rentals', fn (Builder $q) => $q->activeOn($date));
    }
}
