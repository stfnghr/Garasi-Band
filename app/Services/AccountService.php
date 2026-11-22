<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis profil & akun pengguna (ubah profil, ganti password, hapus akun).
 */
class AccountService
{
    /**
     * Perbarui data profil yang boleh diubah (nama & telepon). Email dan NIK tidak bisa diubah.
     *
     * @param  array{name: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data): void
    {
        $user->update(['name' => $data['name'], 'phone' => $data['phone'] ?? $user->phone]);
    }

    /** Ganti password (password lama sudah dicek oleh FormRequest). */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->update(['password' => $newPassword]);
    }

    /**
     * Hapus akun user.
     *
     * Transaksi lama TIDAK dihapus karena dibutuhkan untuk laporan pendapatan, maka:
     * 1. akun ditolak dihapus bila masih punya sewa aktif hari ini,
     * 2. email, NIK, dan telepon dianonimkan agar bisa dipakai mendaftar ulang
     *    (akun baru itu kosong, tidak membawa data lama),
     * 3. akun di-soft-delete sehingga tidak bisa login lagi.
     *
     * @throws ValidationException bila akun admin atau masih punya sewa aktif
     */
    public function deleteAccount(User $user): void
    {
        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['account' => 'Admin accounts cannot be deleted from the profile page.']);
        }

        if (Rental::where('user_id', $user->id)->activeOn(today())->exists()) {
            throw ValidationException::withMessages(['account' => 'The account cannot be deleted because it still has an active rental.']);
        }

        DB::transaction(function () use ($user) {
            $user->update([
                'email' => "deleted-{$user->id}-".now()->timestamp.'@deleted-account.local',
                'nik' => null,
                'phone' => null,
            ]);
            $user->delete();
        });
    }
}
