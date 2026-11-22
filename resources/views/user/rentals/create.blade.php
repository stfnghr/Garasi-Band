{{--
    User › Sewa Garasi Baru (UC08), 2 langkah:
      1. Isi kendaraan & durasi → tombol "Find Available Garages" (GET, tidak menyimpan apa pun).
      2. Pilih garasi yang tersedia hari ini + upload bukti pembayaran → "Save Rental" (POST).
    Aturan tampilan:
    - User mengisi DURASI (jumlah hari). Tanggal mulai selalu HARI INI.
      Tanggal selesai (inklusif) dihitung otomatis oleh sistem dan hanya ditampilkan.
    - Tipe garasi otomatis untuk sepeda/motor (S) dan mobil (C); hanya mobil listrik memilih C atau E.
    - Kapasitas & sisa baterai hanya angka positif (tanpa huruf "e" dan tanda minus).
--}}
@extends('layouts.app')
@section('title', 'New Rental')

@section('content')
@php
    $val = fn ($key, $default = null) => old($key, $criteria[$key] ?? request($key, $default));
    $vehicleIcons = ['sepeda' => 'bi-bicycle', 'motor' => 'bi-scooter', 'mobil' => 'bi-car-front', 'mobil_listrik' => 'bi-ev-front'];
    $selectedVehicle = $val('vehicle_type', 'mobil');
@endphp

<div class="mb-4">
    <h1 class="page-title">New Garage Rental</h1>
    <p class="page-subtitle">The rental always starts today, {{ today()->format('l, j F Y') }}.</p>
</div>

<form method="GET" action="{{ route('user.rentals.create') }}" id="criteria-form" data-validate novalidate>
    <div class="card mb-4">
        <div class="card-header"><span class="step-badge">1</span>Vehicle & Rental Period</div>
        <div class="card-body p-4">
            {{-- Tipe kendaraan --}}
            <label class="form-label">Vehicle Type</label>
            <div class="row g-2 mb-4">
                @foreach ($vehicleTypes as $type)
                    <div class="col-6 col-md-3">
                        <input type="radio" class="btn-check" name="vehicle_type" id="vt-{{ $type->value }}" value="{{ $type->value }}" data-label="Vehicle type"
                               @checked($selectedVehicle === $type->value) required>
                        <label class="choice-card d-block text-center" for="vt-{{ $type->value }}">
                            <i class="bi {{ $vehicleIcons[$type->value] }} fs-3 d-block"></i>
                            <span class="fw-semibold">{{ $type->label() }}</span>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-md-6" data-show="plate">
                    <label class="form-label" for="plate_number">Plate Number</label>
                    <input class="form-control text-uppercase" id="plate_number" name="plate_number" value="{{ $val('plate_number') }}" maxlength="15" placeholder="L 1234 AB" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="brand_model">Brand and Model</label>
                    <input class="form-control" id="brand_model" name="brand_model" value="{{ $val('brand_model') }}" maxlength="100" placeholder="e.g. Toyota Avanza" required>
                </div>
                <div class="col-md-6" data-show="battery">
                    <label class="form-label" for="battery_capacity_kwh">Full Battery Capacity</label>
                    <div class="input-group">
                        <input class="form-control" id="battery_capacity_kwh" name="battery_capacity_kwh" value="{{ $val('battery_capacity_kwh') }}" data-input="decimal" inputmode="decimal" placeholder="e.g. 72.6" required>
                        <span class="input-group-text">kWh</span>
                    </div>
                </div>
                <div class="col-md-6" data-show="battery">
                    <label class="form-label" for="remaining_battery_kwh">Remaining Battery on Arrival</label>
                    <div class="input-group">
                        <input class="form-control" id="remaining_battery_kwh" name="remaining_battery_kwh" value="{{ $val('remaining_battery_kwh') }}" data-input="decimal" inputmode="decimal" data-max-of="#battery_capacity_kwh" placeholder="0 up to capacity" required>
                        <span class="input-group-text">kWh</span>
                    </div>
                </div>

                {{-- Tipe garasi: pilihan hanya untuk mobil listrik; selain itu otomatis --}}
                <div class="col-12" data-show="garage-choice">
                    <label class="form-label">Park in</label>
                    <div class="row g-2">
                        @foreach ([\App\Enums\GarageType::MobilListrik, \App\Enums\GarageType::Mobil] as $gt)
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="garage_type" id="gt-{{ $gt->value }}" value="{{ $gt->value }}" data-label="Garage type"
                                       @checked($val('garage_type', 'mobil_listrik') === $gt->value) required>
                                <label class="choice-card d-block" for="gt-{{ $gt->value }}">
                                    <span class="badge-type type-{{ $gt->color() }}"><i class="bi {{ $gt->icon() }} me-1"></i>{{ $gt->label() }}</span>
                                    <div class="small text-secondary mt-2">
                                        {{ $gt === \App\Enums\GarageType::MobilListrik ? 'Charged to full: (capacity − remaining) × kWh price.' : 'No charging, no charging fee.' }}
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-12" data-show="garage-auto">
                    <div class="alert alert-light border d-flex align-items-center gap-2 mb-0 py-2">
                        <i class="bi bi-info-circle text-primary"></i><span id="garage-auto-text"></span>
                    </div>
                </div>

                {{-- Tipe sewa dipilih dulu; batas jumlah hari mengikuti tipe dan jumlah hari bulan ini --}}
                <div class="col-12">
                    <label class="form-label">Rental Type</label>
                    <div class="row g-2">
                        @foreach (['daily' => 'Daily', 'monthly' => 'Monthly'] as $rtValue => $rtLabel)
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="rental_type" id="rt-{{ $rtValue }}" value="{{ $rtValue }}" data-label="Rental type"
                                       @checked($val('rental_type', 'daily') === $rtValue) required>
                                <label class="choice-card d-block" for="rt-{{ $rtValue }}">
                                    <span class="fw-semibold">{{ $rtLabel }}</span>
                                    <div class="small text-secondary mt-1" data-rule="{{ $rtValue }}"></div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Durasi diisi user; tanggal mulai & selesai dihitung dan hanya ditampilkan --}}
                <div class="col-md-4">
                    <label class="form-label" for="total_days">Rental Duration</label>
                    <div class="input-group">
                        <input class="form-control" id="total_days" name="total_days" value="{{ $val('total_days') }}" data-input="digits" maxlength="4" inputmode="numeric"
                               data-label="Rental duration" placeholder="e.g. 30" required>
                        <span class="input-group-text">days</span>
                    </div>
                    <div class="form-text" id="billing-hint"></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="start-text">Start Date</label>
                    <input class="form-control" id="start-text" value="{{ today()->format('d M Y') }}" readonly>
                    <div class="form-text">Always today.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end-text">End Date (inclusive)</label>
                    <input class="form-control" id="end-text" value="" readonly placeholder="Calculated automatically">
                    <div class="form-text">Calculated from the duration.</div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent text-end p-3">
            <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Find Available Garages</button>
        </div>
    </div>
</form>

@if ($criteria)
    @php $q = $options->first()['quote'] ?? null; @endphp
    <div class="card" id="step-2">
        <div class="card-header"><span class="step-badge">2</span>Choose a Garage & Upload Payment Proof</div>
        <div class="card-body p-4">
            @if ($options->isEmpty())
                <div class="empty-state"><i class="bi bi-emoji-frown"></i>No garage of this type is available today.</div>
            @else
                <div class="row g-3 mb-4 small">
                    <div class="col-sm-3"><div class="text-secondary">Start</div><strong>{{ $q['start_date']->format('d-m-Y') }}</strong></div>
                    <div class="col-sm-3"><div class="text-secondary">End (inclusive)</div><strong>{{ $q['end_date']->format('d-m-Y') }}</strong></div>
                    <div class="col-sm-3"><div class="text-secondary">Duration</div><strong>{{ $criteria['total_days'] }} day(s)</strong></div>
                    <div class="col-sm-3"><div class="text-secondary">Rental type</div>
                        <strong>{{ $q['months'] > 0 ? $q['months'].' month(s)'.($q['days'] ? ' + '.$q['days'].' day(s)' : '') : $q['days'].' day(s) (daily)' }}</strong>
                        <div class="text-secondary">monthly threshold {{ $q['days_in_month'] }} days</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('user.rentals.store') }}" enctype="multipart/form-data" id="rent-form" data-validate novalidate
                      data-confirm="Save this rental?" data-confirm-builder="rent" data-confirm-button="Yes, save">
                    @csrf
                    @foreach ($criteria as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <div class="row g-2 mb-4" style="max-height: 420px; overflow-y: auto;">
                        @foreach ($options as $i => $option)
                            @php $g = $option['garage']; $oq = $option['quote']; @endphp
                            <div class="col-md-6 col-xl-4">
                                <input type="radio" class="btn-check" name="garage_id" id="g{{ $g->id }}" value="{{ $g->id }}" data-label="Garage"
                                       data-code="{{ $g->code }}" data-total="Rp {{ number_format($oq['total_cost'], 0, ',', '.') }}"
                                       @checked((int) old('garage_id', $i === 0 ? $g->id : 0) === $g->id) required>
                                <label class="choice-card d-block" for="g{{ $g->id }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold fs-5">{{ $g->code }}</span>
                                        <span class="badge-type type-{{ $g->type->color() }}">@rupiah($g->daily_price)/day</span>
                                    </div>
                                    <div class="small text-secondary mt-2">Rent @rupiah($oq['rent_cost'])
                                        @if ($oq['charging_cost'] > 0) · Charging @rupiah($oq['charging_cost']) @endif</div>
                                    <div class="fw-bold mt-1">Total @rupiah($oq['total_cost'])</div>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label" for="payment_proof">Payment Proof</label>
                            <input class="form-control" type="file" id="payment_proof" name="payment_proof" accept="image/*" required>
                            <div class="form-text">Image (JPG/PNG), max 2 MB.</div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>Save Rental</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('criteria-form');
    const autoGarage = { sepeda: 'the Bike Garage (S)', motor: 'the Bike Garage (S)', mobil: 'the Car Garage (C)' };
    const vehicle = () => form.querySelector('[name="vehicle_type"]:checked')?.value;

    /** Tampilkan / sembunyikan blok sesuai tipe kendaraan. Field tersembunyi dinonaktifkan agar tidak ikut terkirim. */
    function toggle(name, show) {
        form.querySelectorAll(`[data-show="${name}"]`).forEach((box) => {
            box.hidden = !show;
            box.querySelectorAll('input').forEach((i) => { i.disabled = !show; });
        });
    }
    function refreshVehicle() {
        const v = vehicle();
        toggle('plate', v !== 'sepeda');
        toggle('battery', v === 'mobil_listrik');
        toggle('garage-choice', v === 'mobil_listrik');
        toggle('garage-auto', v !== 'mobil_listrik');
        document.getElementById('garage-auto-text').textContent = `This vehicle is automatically parked in ${autoGarage[v] ?? ''}.`;
    }
    form.querySelectorAll('[name="vehicle_type"]').forEach((r) => r.addEventListener('change', refreshVehicle));
    refreshVehicle();

    // ---- Durasi diisi user; tanggal selesai dihitung di sini (hanya tampilan, server menghitung ulang) ----
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const durationInput = document.getElementById('total_days');
    const endText = document.getElementById('end-text');
    const hint = document.getElementById('billing-hint');
    const daysInMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate(); // ambang bulanan (BR-09)
    const fmt = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    const rentalType = () => form.querySelector('[name="rental_type"]:checked')?.value ?? 'daily';

    // Keterangan aturan di setiap kartu tipe sewa (ikut jumlah hari bulan ini).
    form.querySelector('[data-rule="daily"]').textContent = `1 to ${daysInMonth - 1} day(s)`;
    form.querySelector('[data-rule="monthly"]').textContent = `${daysInMonth} day(s) or more (billed per month)`;

    /** Perbarui tanggal selesai (hari ini + durasi - 1) dan rincian. Aturan batas hari dicek juga di server. */
    function refreshPeriod() {
        const days = parseInt(durationInput.value, 10);
        const type = rentalType();
        if (!days || days < 1) {
            endText.value = '';
            hint.textContent = 'Enter the number of days.';
            return;
        }
        const end = new Date(today); end.setDate(end.getDate() + days - 1);
        endText.value = fmt.format(end);
        if (type === 'daily' && days > daysInMonth - 1) {
            hint.textContent = `Daily rental is at most ${daysInMonth - 1} day(s) this month. Choose Monthly.`;
            hint.classList.add('text-danger');
            return;
        }
        if (type === 'monthly' && days < daysInMonth) {
            hint.textContent = `Monthly rental needs at least ${daysInMonth} day(s) this month. Choose Daily.`;
            hint.classList.add('text-danger');
            return;
        }
        hint.classList.remove('text-danger');
        const months = Math.floor(days / daysInMonth), rest = days % daysInMonth;
        hint.textContent = months ? `${months} month(s)${rest ? ' + ' + rest + ' day(s)' : ''} (monthly)` : `${days} day(s) (daily)`;
    }
    durationInput.addEventListener('input', refreshPeriod);
    form.querySelectorAll('[name="rental_type"]').forEach((r) => r.addEventListener('change', refreshPeriod));
    refreshPeriod();

    // Ringkasan di pop-up konfirmasi simpan sewa.
    window.App.confirmBuilders.rent = (rentForm) => {
        const g = rentForm.querySelector('[name="garage_id"]:checked');
        return `Garage <strong>${App.escapeHtml(g.dataset.code)}</strong>, total <strong>${App.escapeHtml(g.dataset.total)}</strong>.`
            + '<br><small class="text-secondary">Make sure the payment proof matches the total.</small>';
    };

    document.getElementById('step-2')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
})();
</script>
@endpush
