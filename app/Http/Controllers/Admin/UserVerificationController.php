<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin: verifikasi pendaftar user baru. */
class UserVerificationController extends Controller
{
    public function __construct(private readonly UserVerificationService $verifier) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'pending' => $this->verifier->pending(),
            'users' => User::where('role', UserRole::User)->whereNot('status', 'pending')->latest('verified_at')->get(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->verifier->approve($user, $request->user());

        return back()->with('success', "{$user->name} has been approved.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->verifier->reject($user, $request->user());

        return back()->with('success', "{$user->name} has been rejected.");
    }
}
