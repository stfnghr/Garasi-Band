{{--
    Pengaturan Profil (Admin & User).
    - Data profil: nama & telepon bisa diubah; email dan NIK hanya ditampilkan (tidak bisa diubah).
    - Ganti password: wajib password lama.
    - Hapus akun (khusus user): konfirmasi pop-up + password. Transaksi lama tetap tersimpan
      untuk laporan, tetapi email/NIK dibebaskan sehingga bisa mendaftar ulang sebagai akun baru yang kosong.
--}}
@extends('layouts.app')
@section('title', 'Profile Settings')
@section('content')
<div class="mb-4">
    <h1 class="page-title">Profile Settings</h1>
    <p class="page-subtitle">Manage your personal data and account security.</p>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <form class="card h-100" method="POST" action="{{ route('profile.update') }}" data-validate novalidate
              data-confirm="Save profile changes?" data-confirm-button="Yes, save">
            @csrf @method('PATCH')
            <div class="card-header"><i class="bi bi-person me-2"></i>Profile</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" value="{{ $user->email }}" disabled>
                    <div class="form-text">Email is your username and cannot be changed.</div>
                </div>
                @if ($user->nik)
                    <div class="mb-3">
                        <label class="form-label" for="nik">NIK</label>
                        <input class="form-control font-monospace" id="nik" value="{{ $user->nik }}" disabled>
                    </div>
                @endif
                <div class="mb-3">
                    <label class="form-label" for="name">Full Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="50" required>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input class="form-control" type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" data-input="phone" inputmode="numeric" maxlength="18" data-min-digits="10" @if (! $user->isAdmin()) required @endif>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Profile</button></div>
        </form>
    </div>

    <div class="col-lg-6">
        <form class="card h-100" method="POST" action="{{ route('profile.password') }}" data-validate novalidate
              data-confirm="Change password?" data-confirm-text="Use the new password the next time you log in." data-confirm-button="Yes, change">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Change Password</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New Password</label>
                    <input class="form-control" type="password" id="new_password" name="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="new_password_confirmation">Confirm New Password</label>
                    <input class="form-control" type="password" id="new_password_confirmation" name="password_confirmation" data-match="#new_password" autocomplete="new-password" required>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-key me-1"></i>Change Password</button></div>
        </form>
    </div>

    @unless ($user->isAdmin())
        <div class="col-12">
            <form class="card danger-zone" method="POST" action="{{ route('profile.destroy') }}" data-validate novalidate
                  data-confirm="Delete your account?" data-confirm-text="You will be logged out and cannot log in with this account again. Transaction history is kept for reports. This cannot be undone."
                  data-confirm-button="Yes, delete account" data-confirm-icon="warning" data-confirm-danger>
                @csrf @method('DELETE')
                <div class="card-header text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Account</div>
                <div class="card-body row g-3 align-items-end">
                    <div class="col-md-7 small text-secondary">
                        The account can only be deleted when you have no active rental. Afterwards, your email and NIK
                        can be used to register again as a new, empty account.
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="delete_password">Password</label>
                        <input class="form-control" type="password" id="delete_password" name="delete_password" autocomplete="current-password" required>
                    </div>
                    <div class="col-md-2"><button class="btn btn-danger w-100"><i class="bi bi-trash me-1"></i>Delete</button></div>
                </div>
            </form>
        </div>
    @endunless
</div>
@endsection
@push('scripts')
<script>
    // Password baru minimal 8 karakter (dicek juga di server).
    document.querySelectorAll('form[data-validate]')[1].dataset.validate = 'password';
    window.App.validators.password = (form) => form.password.value && form.password.value.length < 8 ? ['New password must be at least 8 characters.'] : [];
</script>
@endpush
