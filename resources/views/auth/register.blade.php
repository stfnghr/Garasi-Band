{{--
    Halaman pendaftaran user (UC01).
    Aturan input (dicek di browser oleh app.js DAN di server oleh RegisterUserRequest):
    - NIK   : hanya angka, tepat 16 digit (data-input="digits", maxlength 16)
    - Telp  : hanya angka, maks 15 digit, tampil "0812-3456-7890" (data-input="phone")
    - Nama  : maks 50 karakter; Email: maks 50 karakter dan wajib domain lengkap (.com, .id, ...)
--}}
@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="auth-wrap">
    <div class="card auth-card shadow-sm">
        <div class="row g-0">
            @include('auth._side')
            <div class="col-lg-7 p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Create an Account</h1>
                <p class="text-secondary mb-4">Your account becomes active after an admin verifies your ID card (KTP).</p>

                <form method="POST" action="{{ url('/register') }}" data-validate novalidate
                      data-confirm="Submit registration?" data-confirm-text="Make sure your NIK and details are correct. NIK and email cannot be changed later." data-confirm-button="Yes, register">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="nik">NIK (ID Card Number)</label>
                            <input class="form-control" id="nik" name="nik" value="{{ old('nik') }}" data-input="digits" inputmode="numeric" maxlength="16" data-exact-digits="16" placeholder="16 digits" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="name">Full Name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" maxlength="50" placeholder="As on your ID card" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input class="form-control" type="tel" id="phone" name="phone" value="{{ old('phone') }}" data-input="phone" inputmode="numeric" maxlength="18" data-min-digits="10" placeholder="0812-3456-7890" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email (username)</label>
                            <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" maxlength="50" placeholder="name@gmail.com" autocomplete="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" data-match="#password" autocomplete="new-password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 py-2 mt-4"><i class="bi bi-person-plus me-1"></i>Register</button>
                </form>
                <p class="mt-4 mb-0 text-center text-secondary">Already have an account? <a href="{{ route('login') }}" class="fw-semibold">Log in</a></p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Validasi tambahan khusus form daftar: password minimal 8 karakter.
    document.querySelector('form[data-validate]').dataset.validate = 'register';
    window.App.validators.register = (form) => {
        const pass = form.querySelector('#password');
        return pass.value && pass.value.length < 8 ? ['Password must be at least 8 characters.'] : [];
    };
</script>
@endpush
