{{-- Halaman error 404 yang senada dengan tampilan aplikasi. --}}
@extends('layouts.app')
@section('title', '404')
@section('content')
<div class="auth-wrap">
    <div class="text-center">
        <div class="display-3 fw-bold text-primary">404</div>
        <h1 class="h4 fw-bold">{{ $exception->getMessage() ?: 'Page not found.' }}</h1>
        <p class="text-secondary">Please go back to the home page.</p>
        <a href="{{ url('/') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i>Back to Home</a>
    </div>
</div>
@endsection
