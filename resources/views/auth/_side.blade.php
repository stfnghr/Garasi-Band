{{-- Panel kiri halaman auth: identitas aplikasi & ringkasan fitur (disembunyikan di layar kecil) --}}
<div class="col-lg-5 auth-side d-none d-lg-flex flex-column justify-content-between">
    <div>
        <div class="d-flex align-items-center gap-2 fs-5 fw-bold"><i class="bi bi-p-square-fill"></i>{{ config('app.name') }}</div>
        <h2 class="fw-bold mt-4 mb-2">Park it. We keep it.</h2>
        <p class="opacity-75 mb-0">Daily or monthly garages for every vehicle.</p>
        <div class="feature"><i class="bi bi-bicycle fs-5"></i><div>Bike garage for bicycles & motorcycles</div></div>
        <div class="feature"><i class="bi bi-car-front fs-5"></i><div>Car garage for petrol & electric cars</div></div>
        <div class="feature"><i class="bi bi-ev-front fs-5"></i><div>EV garage with full charging</div></div>
    </div>
    <small class="opacity-75">Monthly rate = 25&times; the daily rate.</small>
</div>
