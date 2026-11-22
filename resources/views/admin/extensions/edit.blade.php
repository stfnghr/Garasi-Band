{{--
    Admin › Perpanjang Sewa satu garasi.
    1. Pilih tanggal selesai baru → "Hitung Sisa Biaya" (GET, belum menyimpan).
    2. Upload bukti bayar → "Simpan Perpanjangan" (PUT) dengan konfirmasi.
    Sisa biaya SELALU = tambahan hari × harga harian (tidak pernah dikonversi ke paket bulanan).
--}}
@extends('layouts.app')
@section('title', 'Extend '.$rental->garage->code)
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush
@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.extensions.index') }}" class="btn btn-light border" title="Back"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h1 class="page-title">Extend Rental of Garage {{ $rental->garage->code }}</h1>
        <p class="page-subtitle">{{ $rental->user->name }} · {{ $rental->vehicle->brand_model }} {{ $rental->vehicle->plate_number }}</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Rental Details</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-6 text-secondary fw-normal">Garage type</dt><dd class="col-6"><span class="badge-type type-{{ $rental->garage->type->color() }}">{{ $rental->garage->type->label() }}</span></dd>
                    <dt class="col-6 text-secondary fw-normal">Vehicle</dt><dd class="col-6">{{ $rental->vehicle->type->label() }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Initial billing</dt><dd class="col-6">{{ $rental->billingLabel() }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Start</dt><dd class="col-6">{{ $rental->start_date->format('d-m-Y') }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Current end</dt><dd class="col-6 fw-semibold">{{ $rental->end_date->format('d-m-Y') }}</dd>
                    <dt class="col-6 text-secondary fw-normal">Daily price</dt><dd class="col-6">@rupiah($rental->daily_price)</dd>
                    <dt class="col-6 text-secondary fw-normal">Total paid</dt><dd class="col-6 fw-semibold">@rupiah($rental->payments->sum('amount'))</dd>
                </dl>
                <hr>
                <div class="small fw-semibold mb-2">Payment proofs</div>
                <ul class="small ps-3 mb-0">
                    @foreach ($rental->payments as $payment)
                        <li>{{ $payment->paid_at->format('d-m-Y') }} — {{ $payment->type->label() }}{{ $payment->extra_days ? " ({$payment->extra_days} day(s))" : '' }}
                            — @rupiah($payment->amount) — <a href="{{ asset('storage/'.$payment->proof_path) }}" target="_blank" rel="noopener">view</a></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Extension</div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.extensions.edit', $rental) }}" class="mb-3" data-validate novalidate>
                    <label class="form-label" for="new_end_date_check">New End Date</label>
                    <div class="input-group">
                        <input class="form-control" id="new_end_date_check" name="new_end_date" value="{{ request('new_end_date') }}" placeholder="Pick a date" required>
                        <button class="btn btn-secondary"><i class="bi bi-calculator me-1"></i>Calculate Amount Due</button>
                    </div>
                    <div class="form-text">Must be after {{ $rental->end_date->format('d-m-Y') }}.</div>
                </form>

                @if ($cost !== null)
                    <div class="alert alert-primary border-0">
                        Extra <strong>{{ $extraDays }} day(s)</strong> &times; @rupiah($rental->daily_price) (always the daily rate)
                        <div class="fs-4 fw-bold mt-1">Amount due @rupiah($cost)</div>
                    </div>
                    <form method="POST" action="{{ route('admin.extensions.update', $rental) }}" enctype="multipart/form-data" data-validate novalidate
                          data-confirm="Save extension?" data-confirm-text="Rental {{ $rental->garage->code }} will be extended by {{ $extraDays }} day(s) until {{ \Illuminate\Support\Carbon::parse(request('new_end_date'))->format('d-m-Y') }} for Rp {{ number_format($cost, 0, ',', '.') }}." data-confirm-button="Yes, save">
                        @csrf @method('PUT')
                        <input type="hidden" name="new_end_date" value="{{ request('new_end_date') }}">
                        <div class="mb-3">
                            <label class="form-label" for="payment_proof">Extension Payment Proof</label>
                            <input class="form-control" type="file" id="payment_proof" name="payment_proof" accept="image/*" required>
                            <div class="form-text">Image (JPG/PNG), max 2 MB.</div>
                        </div>
                        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Extension</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script>
    // Kalender: hanya tanggal setelah tanggal selesai saat ini yang bisa dipilih.
    flatpickr('#new_end_date_check', {
        dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y', altInputClass: 'form-control',
        minDate: @json($rental->end_date->addDay()->toDateString()),
    });
</script>
@endpush
