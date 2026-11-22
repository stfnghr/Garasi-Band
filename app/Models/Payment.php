<?php

namespace App\Models;

use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti bayar. Satu sewa bisa punya banyak pembayaran (sewa baru + tiap perpanjangan).
 */
#[Fillable(['rental_id', 'uploaded_by', 'type', 'amount', 'extra_days', 'previous_end_date', 'new_end_date', 'proof_path', 'paid_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'amount' => 'decimal:2',
            'extra_days' => 'integer',
            'previous_end_date' => 'date',
            'new_end_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /** Transaksi sewa yang dibayar. */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** Pengguna yang mengunggah bukti (user atau admin). */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
