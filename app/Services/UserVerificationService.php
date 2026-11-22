<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi pendaftar user baru oleh admin.
 */
class UserVerificationService
{
    /** Pendaftar yang menunggu verifikasi. */
    public function pending(): Collection
    {
        return User::where('role', UserRole::User)->where('status', UserStatus::Pending)->oldest()->get();
    }

    /** Terima pendaftar: status menjadi approved. */
    public function approve(User $user, User $admin): void
    {
        $this->decide($user, $admin, UserStatus::Approved);
    }

    /** Tolak pendaftar: status menjadi rejected. */
    public function reject(User $user, User $admin): void
    {
        $this->decide($user, $admin, UserStatus::Rejected);
    }

    /**
     * Simpan keputusan verifikasi beserta admin dan waktunya.
     *
     * @throws ValidationException bila pengguna bukan user berstatus pending
     */
    private function decide(User $user, User $admin, UserStatus $status): void
    {
        if ($user->role !== UserRole::User || $user->status !== UserStatus::Pending) {
            throw ValidationException::withMessages(['user' => 'This applicant has already been verified.']);
        }

        $user->update(['status' => $status, 'verified_by' => $admin->id, 'verified_at' => now()]);
    }
}
