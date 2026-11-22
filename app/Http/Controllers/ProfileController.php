<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Controller pengaturan profil (Admin & User): ubah profil, ganti password, hapus akun.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    /** Halaman pengaturan profil. */
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /** Simpan perubahan nama & telepon. */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->accounts->updateProfile($request->user(), $request->validated());

        return back()->with('success', 'Profile updated.');
    }

    /** Ganti password: wajib memasukkan password lama yang benar. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Current password is incorrect.',
            'password.different' => 'New password must be different from the current one.',
        ], ['current_password' => 'current password', 'password' => 'new password']);

        $this->accounts->changePassword($request->user(), $data['password']);

        return back()->with('success', 'Password changed.');
    }

    /** Hapus akun user (dikonfirmasi dengan password), lalu logout. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(
            ['delete_password' => ['required', 'current_password']],
            ['delete_password.current_password' => 'Incorrect password, account not deleted.'],
            ['delete_password' => 'password']
        );

        $this->accounts->deleteAccount($request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Your account has been deleted. You can register again with the same email.');
    }
}
