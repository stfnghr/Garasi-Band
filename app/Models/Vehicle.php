<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kendaraan milik user.
 */
#[Fillable(['user_id', 'type', 'plate_number', 'brand_model', 'battery_capacity_kwh'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'battery_capacity_kwh' => 'decimal:2',
        ];
    }

    /** User pemilik kendaraan. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Riwayat sewa kendaraan ini. */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
