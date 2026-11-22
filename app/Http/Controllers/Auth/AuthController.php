<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Login, logout, dan pendaftaran user. */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Incorrect email or password.'])->onlyInput('email');
        }

        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isApproved()) {
            Auth::logout();
            $message = $user->status === UserStatus::Rejected
                ? 'Your registration was rejected by the admin.'
                : 'Your account is still waiting for admin verification.';

            return back()->withErrors(['email' => $message])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(self::homeFor($user));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterUserRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'role' => UserRole::User,
            'status' => UserStatus::Pending,
        ]);

        return redirect()->route('login')->with('success', 'Registration successful. Please wait for the admin to verify your ID card before logging in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Halaman awal sesuai role. */
    public static function homeFor(User $user): string
    {
        return $user->isAdmin() ? route('admin.garages.index') : route('user.rentals.index');
    }
}
