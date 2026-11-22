{{--
    Admin › Buat Garasi Berkelompok (UC05).
    - Tipe dipilih lewat kartu; jumlah hanya angka 1–999 (maks 3 digit, tanpa minus).
    - Harga diketik dengan format Rupiah otomatis ("Rp 20.000"); server membuang titik & "Rp".
    - Sebelum simpan muncul pop-up konfirmasi berisi ringkasan & pratinjau kode garasi yang akan dibuat.
--}}
@extends('layouts.app')
@section('title', 'Create Garages')
@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('admin.garages.index') }}" class="btn btn-light border" title="Back to garages"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h1 class="page-title">Create Garages in Batch</h1>
        <p class="page-subtitle">Garage IDs are generated automatically, continuing from the last number of each type.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form class="card" method="POST" action="{{ route('admin.garages.store') }}" data-validate="garage" novalidate
              data-confirm="Create new garages?" data-confirm-builder="garage" data-confirm-button="Yes, create">
            @csrf
            <div class="card-body p-4">
                <label class="form-label">Garage Type</label>
                <div class="row g-2 mb-4">
                    @foreach ($garageTypes as $t)
                        <div class="col-md-4">
                            <input type="radio" class="btn-check" name="type" id="type-{{ $t->value }}" value="{{ $t->value }}" data-label="Garage type"
                                   @checked(old('type', 'sepeda') === $t->value) required>
                            <label class="choice-card d-block" for="type-{{ $t->value }}">
                                <span class="badge-type type-{{ $t->color() }}"><i class="bi {{ $t->icon() }}"></i></span>
                                <div class="fw-semibold mt-2">{{ $t->label() }}</div>
                                <div class="small text-secondary">Code {{ $t->prefix() }}001 – {{ $t->prefix() }}999</div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="count">Number of Garages</label>
                        <input class="form-control" id="count" name="count" value="{{ old('count') }}" data-input="digits" inputmode="numeric" maxlength="3" placeholder="1 – 999" required>
                        <div class="form-text" id="count-hint"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="daily_price">Daily Price</label>
                        <input class="form-control" id="daily_price" name="daily_price" value="{{ old('daily_price') }}" data-input="rupiah" inputmode="numeric" placeholder="Rp 0" required>
                        <div class="form-text" id="monthly-hint">Monthly = 25&times; daily</div>
                    </div>
                    <div class="col-md-4" id="kwh-box">
                        <label class="form-label" for="kwh_price">Charging Price per kWh</label>
                        <input class="form-control" id="kwh_price" name="kwh_price" value="{{ old('kwh_price') }}" data-input="rupiah" inputmode="numeric" placeholder="Rp 0" required>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2 p-3">
                <a href="{{ route('admin.garages.index') }}" class="btn btn-light border">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Create Garages</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('form[data-validate="garage"]');
    const lastSequences = @json($lastSequences);          // nomor terakhir per tipe, contoh {"mobil": 10}
    const prefixes = @json(collect($garageTypes)->mapWithKeys(fn ($t) => [$t->value => $t->prefix()]));
    const labels = @json(collect($garageTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()]));
    const digits = (v) => Number(String(v).replace(/\D/g, '') || 0);
    const code = (type, n) => prefixes[type] + String(n).padStart(3, '0');
    const type = () => form.querySelector('[name="type"]:checked')?.value;

    /** Tampilkan field kWh hanya untuk Mobil Listrik & perbarui petunjuk sisa kuota. */
    function refresh() {
        const t = type();
        const box = document.getElementById('kwh-box');
        box.hidden = t !== 'mobil_listrik';
        form.kwh_price.disabled = box.hidden;
        const last = Number(lastSequences[t] || 0);
        document.getElementById('count-hint').textContent = `${999 - last} IDs left (last: ${last ? code(t, last) : 'none'}).`;
        const daily = digits(form.daily_price.value);
        document.getElementById('monthly-hint').textContent = daily ? `Monthly = Rp ${App.thousands(String(daily * 25))}` : 'Monthly = 25× daily';
    }
    form.querySelectorAll('[name="type"]').forEach((r) => r.addEventListener('change', refresh));
    form.daily_price.addEventListener('input', refresh);
    refresh();

    // Validasi tambahan: jumlah 1–999 dan tidak melebihi sisa kuota tipe tersebut.
    window.App.validators.garage = () => {
        const n = digits(form.count.value);
        const remaining = 999 - Number(lastSequences[type()] || 0);
        if (form.count.value && n < 1) return ['Number of garages must be at least 1.'];
        if (n > remaining) return [`Number exceeds the remaining ID quota (${remaining} left).`];
        if (form.daily_price.value && digits(form.daily_price.value) < 1) return ['Daily price must be at least Rp 1.'];
        return [];
    };

    // Isi pop-up konfirmasi: ringkasan & pratinjau kode.
    window.App.confirmBuilders.garage = () => {
        const t = type(), n = digits(form.count.value), last = Number(lastSequences[t] || 0);
        const range = n > 1 ? `${code(t, last + 1)} – ${code(t, last + n)}` : code(t, last + 1);
        let html = `<div class="text-start small"><table class="table table-sm mb-0">
            <tr><td>Type</td><td class="fw-semibold">${labels[t]}</td></tr>
            <tr><td>Count</td><td class="fw-semibold">${n} garage(s) (${range})</td></tr>
            <tr><td>Daily price</td><td class="fw-semibold">${App.escapeHtml(form.daily_price.value)}</td></tr>
            <tr><td>Monthly price</td><td class="fw-semibold">Rp ${App.thousands(String(digits(form.daily_price.value) * 25))}</td></tr>`;
        if (t === 'mobil_listrik') html += `<tr><td>Charging</td><td class="fw-semibold">${App.escapeHtml(form.kwh_price.value)} / kWh</td></tr>`;
        return html + '</table></div>';
    };
})();
</script>
@endpush
