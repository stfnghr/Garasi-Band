{{-- Halaman login (Admin & User). Error login ditampilkan sebagai pop-up oleh app.js. --}}
@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="auth-wrap">
    <div class="card auth-card shadow-sm">
        <div class="row g-0">
            @include('auth._side')
            <div class="col-lg-7 p-4 p-md-5">
                <h1 class="h3 fw-bold mb-1">Welcome back</h1>
                <p class="text-secondary mb-4">Log in to manage your garage rentals.</p>

                {{-- novalidate: validasi bawaan browser dimatikan, diganti pop-up dari app.js (data-validate) --}}
                <form method="POST" action="{{ url('/login') }}" data-validate novalidate>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" maxlength="50" placeholder="name@gmail.com" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
                        </div>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Log in</button>
                </form>
                <p class="mt-4 mb-0 text-center text-secondary">No account yet? <a href="{{ route('register') }}" class="fw-semibold">Create an account</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
