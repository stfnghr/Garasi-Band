<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Transaksi sewa garasi.
 *
 * Periode sewa inklusif: garasi dipakai pada tanggal d jika start_date <= d <= end_date.
 * Rincian tagihan sewa awal (billed_months, billed_days) disimpan saat transaksi dibuat
 * dan tidak berubah ketika sewa diperpanjang.
 *
 * @property int $id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $total_days
 * @property int $days_in_month
 * @property int $billed_months
 * @property int $billed_days
 * @property string $daily_price
 * @property string|null $kwh_price
 * @property string|null $remaining_battery_kwh
 * @property string $rent_cost
 * @property string $charging_cost
 * @property string $total_cost
 */
#[Fillable([
    'user_id', 'vehicle_id', 'garage_id', 'start_date', 'end_date',
    'total_days', 'days_in_month', 'billed_months', 'billed_days',
    'daily_price', 'kwh_price', 'remaining_battery_kwh', 'rent_cost', 'charging_cost', 'total_cost',
])]
class Rental extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'integer',
            'days_in_month' => 'integer',
            'billed_months' => 'integer',
            'billed_days' => 'integer',
            'daily_price' => 'decimal:2',
            'kwh_price' => 'decimal:2',
            'remaining_battery_kwh' => 'decimal:2',
            'rent_cost' => 'decimal:2',
            'charging_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    /** User pemilik transaksi. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Garasi yang disewa. */
    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    /** Kendaraan yang ditempatkan. */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Semua bukti bayar (sewa baru + perpanjangan). */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Label rincian tagihan sewa awal, contoh: "1 bulan + 9 hari" atau "25 hari (harian)".
     */
    public function billingLabel(): string
    {
        if ($this->billed_months === 0) {
            return "{$this->billed_days} day(s) (daily)";
        }

        return "{$this->billed_months} month(s)".($this->billed_days > 0 ? " + {$this->billed_days} day(s)" : '');
    }

    /**
     * Query scope: sewa yang sedang berjalan pada tanggal tertentu (end_date inklusif).
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }

    /** Apakah sewa aktif pada hari ini. */
    public function isActiveToday(): bool
    {
        return $this->start_date->lte(today()) && $this->end_date->gte(today());
    }
}
