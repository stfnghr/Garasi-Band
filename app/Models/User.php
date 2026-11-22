<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna aplikasi: Admin atau User.
 *
 * Memakai SoftDeletes: akun yang dihapus user disembunyikan dari login & daftar,
 * tetapi transaksinya tetap tersimpan untuk laporan (lihat AccountService::deleteAccount).
 */
#[Fillable(['nik', 'name', 'phone', 'email', 'password', 'role', 'status', 'verified_by', 'verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /** Kendaraan milik user. */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** Transaksi sewa milik user. */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** Apakah pengguna ber-role admin. */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Apakah akun sudah disetujui admin. */
    public function isApproved(): bool
    {
        return $this->status === UserStatus::Approved;
    }
}
