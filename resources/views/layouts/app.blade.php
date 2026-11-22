{{--
    Layout utama semua halaman.
    - CSS: Bootstrap 5.3 + Bootstrap Icons + font Plus Jakarta Sans + tema public/css/app.css
    - JS : Bootstrap bundle, SweetAlert2 (pop-up), public/js/app.js (perilaku global)
    - Halaman anak mengisi @section('content') dan boleh menambah @push('styles') / @push('scripts').
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Home') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
@auth
    @php
        $user = auth()->user();
        // Menu navigasi sesuai role: [nama route, pola route aktif, ikon, label].
        $menus = $user->isAdmin()
            ? [
                ['admin.garages.index', 'admin.garages.*', 'bi-grid-3x3-gap', 'Garages'],
                ['admin.users.index', 'admin.users.*', 'bi-person-check', 'Verifications'],
                ['admin.extensions.index', 'admin.extensions.*', 'bi-calendar-plus', 'Extensions'],
                ['admin.reports.index', 'admin.reports.*', 'bi-bar-chart-line', 'Reports'],
            ]
            : [
                ['user.rentals.index', 'user.rentals.index', 'bi-house-door', 'Active Rentals'],
                ['user.rentals.create', 'user.rentals.create', 'bi-plus-circle', 'New Rental'],
                ['user.rentals.history', 'user.rentals.history', 'bi-clock-history', 'History'],
            ];
    @endphp
    <nav class="navbar navbar-expand-lg app-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                <span class="brand-mark"><i class="bi bi-p-square-fill"></i></span>{{ config('app.name') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Open menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto gap-lg-1 mt-2 mt-lg-0">
                    @foreach ($menus as [$route, $pattern, $icon, $label])
                        <li class="nav-item">
                            <a class="nav-link @if (request()->routeIs($pattern)) active @endif" href="{{ route($route) }}">
                                <i class="bi {{ $icon }} me-1"></i>{{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                {{-- Menu pengguna: hanya nama (tanpa label role), berisi Profil & Logout --}}
                <div class="dropdown">
                    <button class="btn btn-link text-decoration-none text-reset d-flex align-items-center gap-2 px-0" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="fw-semibold">{{ $user->name }}</span>
                        <i class="bi bi-chevron-down small text-secondary"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>Profile Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" data-confirm="Log out?" data-confirm-text="Your session will end." data-confirm-button="Yes, log out" data-confirm-icon="question">
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
@endauth

<main class="@auth container py-4 @endauth">
    @yield('content')
</main>

{{-- Data flash (pesan sukses & error validasi server) dibaca public/js/app.js lalu ditampilkan sebagai pop-up. --}}
@php($flash = ['success' => session('success'), 'errors' => $errors->all(), 'fields' => $errors->getMessages()])
<script type="application/json" id="app-flash">{!! json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
