{{--
    Admin › Daftar Garasi.
    - Filter tipe berupa tombol (pill) berwarna; kartu ringkasan mengikuti filter aktif.
    - Tabel dipaginasi 10/25/50 baris agar ringan walau ada 999 garasi per tipe.
    - Centang beberapa garasi SATU TIPE untuk "Change Price" (dengan konfirmasi). Garasi tidak bisa dihapus.
    - Klik baris untuk membuka pop-up detail: user yang sedang memakai + riwayat pemakai.
--}}
@extends('layouts.app')
@section('title', 'Garages')
@section('content')
@php
    // Query string yang dipertahankan saat berpindah filter / jumlah baris.
    $baseQuery = fn (array $extra = []) => array_filter([...request()->only(['type', 'per_page']), ...$extra], fn ($v) => $v !== null && $v !== '');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="page-title">Garages</h1>
        <p class="page-subtitle">Manage garage units and prices, and see who is using them.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.garages.create') }}"><i class="bi bi-plus-lg me-1"></i>Create Garages</a>
</div>

{{-- Filter tipe garasi (tombol berjejer) --}}
<div class="filter-pills d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.garages.index', $baseQuery(['type' => null, 'page' => null])) }}" class="btn btn-sm all @if (! $selectedType) active @endif">
        <i class="bi bi-grid me-1"></i>All Types
    </a>
    @foreach ($garageTypes as $t)
        <a href="{{ route('admin.garages.index', $baseQuery(['type' => $t->value, 'page' => null])) }}" class="btn btn-sm {{ $t->color() }} @if ($selectedType === $t) active @endif">
            <i class="bi {{ $t->icon() }} me-1"></i>{{ $t->shortLabel() }} <span class="opacity-75">({{ $t->prefix() }})</span>
        </a>
    @endforeach
</div>

{{-- Kartu ringkasan (mengikuti filter) --}}
<div class="row g-3 mb-4">
    @foreach ([
        ['Total Garages', $summary['total'], 'bi-grid-3x3-gap', 'bg-primary-subtle text-primary'],
        ['Available', $summary['available'], 'bi-check2-circle', 'bg-success-subtle text-success'],
        ['In Use', $summary['used'], 'bi-car-front-fill', 'bg-warning-subtle text-warning-emphasis'],
    ] as [$label, $value, $icon, $color])
        <div class="col-md-4">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon {{ $color }}"><i class="bi {{ $icon }}"></i></div>
                <div>
                    <div class="stat-label">{{ $label }} · {{ $selectedType?->shortLabel() ?? 'All Types' }}</div>
                    <div class="stat-value">{{ number_format($value, 0, ',', '.') }}</div>
                </div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="card">
    {{-- Toolbar: jumlah baris per halaman & aksi massal --}}
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center gap-2 me-auto">
            @if ($selectedType)<input type="hidden" name="type" value="{{ $selectedType->value }}">@endif
            <label for="per_page" class="small text-secondary fw-normal">Show</label>
            <select id="per_page" name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                @foreach ($perPageOptions as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
            <span class="small text-secondary fw-normal">rows</span>
        </form>
        <span class="small text-secondary fw-normal" id="selection-info">No garage selected</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-edit-price" disabled><i class="bi bi-tag me-1"></i>Change Price</button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:42px"><input class="form-check-input" type="checkbox" id="check-all" title="Select all garages of the same type on this page" aria-label="Select all"></th>
                    <th>Code</th><th>Type</th><th class="text-end">Daily Price</th><th class="text-end">Monthly Price</th>
                    <th class="text-end">Charging / kWh</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($garages as $garage)
                <tr class="row-click type-bar-{{ $garage->type->color() }}" data-detail-url="{{ route('admin.garages.show', $garage) }}">
                    <td><input class="form-check-input row-check" type="checkbox" value="{{ $garage->id }}" data-type="{{ $garage->type->value }}"
                               data-daily="{{ (int) $garage->daily_price }}" data-kwh="{{ (int) $garage->kwh_price }}" aria-label="Select {{ $garage->code }}"></td>
                    <td class="fw-bold">{{ $garage->code }}</td>
                    <td><span class="badge-type type-{{ $garage->type->color() }}"><i class="bi {{ $garage->type->icon() }} me-1"></i>{{ $garage->type->shortLabel() }}</span></td>
                    <td class="text-end">@rupiah($garage->daily_price)</td>
                    <td class="text-end text-secondary">@rupiah($garage->daily_price * \App\Services\RentalPriceCalculator::MONTHLY_MULTIPLIER)</td>
                    <td class="text-end">{{ $garage->kwh_price ? 'Rp '.number_format($garage->kwh_price, 0, ',', '.') : '–' }}</td>
                    <td><span class="badge-status status-{{ $garage->status->value }}">{{ $garage->status === \App\Enums\GarageStatus::Used ? 'In use' : 'Available' }}</span></td>
                    <td class="text-end text-secondary"><i class="bi bi-chevron-right"></i></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i>No garages for this filter yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($garages->total() > 0)
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <small class="text-secondary">Showing {{ $garages->firstItem() }}–{{ $garages->lastItem() }} of {{ number_format($garages->total(), 0, ',', '.') }} garages</small>
            {{ $garages->onEachSide(1)->links() }}
        </div>
    @endif
</div>

{{-- Pop-up detail garasi (isi dimuat dari route admin.garages.show) --}}
<div class="modal fade" id="detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" id="detail-content"></div>
    </div>
</div>

{{-- Pop-up ubah harga massal --}}
<div class="modal fade" id="price-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.garages.prices') }}" id="price-form" data-validate novalidate
              data-confirm="Change garage price?" data-confirm-builder="price" data-confirm-button="Yes, change price">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tag me-2"></i>Change Garage Price</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3" id="price-selected"></div>
                <div id="price-ids"></div>
                <div class="mb-3">
                    <label class="form-label" for="price_daily">Daily Price</label>
                    <input class="form-control" id="price_daily" name="daily_price" data-input="rupiah" inputmode="numeric" placeholder="Rp 0" required>
                    <div class="form-text">Monthly price is automatically 25&times; the daily price. Applies to future rentals.</div>
                </div>
                <div class="mb-1" id="price-kwh-box" hidden>
                    <label class="form-label" for="price_kwh">Charging Price per kWh</label>
                    <input class="form-control" id="price_kwh" name="kwh_price" data-input="rupiah" inputmode="numeric" placeholder="Rp 0" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Price</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const checks = [...document.querySelectorAll('.row-check')];
    const checkAll = document.getElementById('check-all');
    const info = document.getElementById('selection-info');
    const btnPrice = document.getElementById('btn-edit-price');
    const typeLabels = @json(collect($garageTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()]));

    const selected = () => checks.filter((c) => c.checked);
    const codeOf = (c) => c.closest('tr').children[1].textContent.trim();

    /**
     * Perbarui status pilihan: hanya garasi bertipe sama yang boleh dipilih bersamaan
     * (Sepeda & Motor berbagi garasi S, Mobil = C, Mobil Listrik = E).
     */
    function refreshSelection() {
        const chosen = selected();
        const type = chosen[0]?.dataset.type;
        checks.forEach((c) => {
            c.disabled = !!type && c.dataset.type !== type;
            c.closest('tr').classList.toggle('opacity-50', c.disabled);
        });
        btnPrice.disabled = chosen.length === 0;
        info.textContent = chosen.length ? `${chosen.length} selected · ${typeLabels[type]}` : 'No garage selected';
        checkAll.checked = chosen.length > 0 && chosen.length === checks.filter((c) => !c.disabled).length;
    }

    checks.forEach((c) => c.addEventListener('change', refreshSelection));

    // Centang semua: memilih semua garasi di halaman ini yang setipe dengan pilihan pertama (atau baris pertama).
    checkAll.addEventListener('change', () => {
        const type = selected()[0]?.dataset.type ?? checks[0]?.dataset.type;
        checks.forEach((c) => { c.checked = checkAll.checked && c.dataset.type === type; });
        refreshSelection();
    });

    /** Isi input tersembunyi ids[] pada form tujuan. */
    function fillIds(containerId) {
        document.getElementById(containerId).innerHTML = selected().map((c) => `<input type="hidden" name="ids[]" value="${c.value}">`).join('');
    }

    // ---- Ubah harga ----
    const priceModal = new bootstrap.Modal('#price-modal');
    btnPrice.addEventListener('click', () => {
        const chosen = selected();
        const isEv = chosen[0].dataset.type === 'mobil_listrik';
        fillIds('price-ids');
        document.getElementById('price-selected').innerHTML =
            `<strong>${chosen.length} ${typeLabels[chosen[0].dataset.type]}</strong>: ${chosen.map(codeOf).join(', ')}`;
        // Isi harga awal dari garasi pertama yang dipilih.
        const daily = document.getElementById('price_daily');
        const kwh = document.getElementById('price_kwh');
        daily.value = chosen[0].dataset.daily; daily.dispatchEvent(new Event('input'));
        document.getElementById('price-kwh-box').hidden = !isEv;
        kwh.disabled = !isEv;
        kwh.value = isEv ? chosen[0].dataset.kwh : ''; kwh.dispatchEvent(new Event('input'));
        priceModal.show();
    });
    window.App.confirmBuilders.price = (form) => {
        const kwhBox = document.getElementById('price-kwh-box');
        return `${selected().length} garage(s) will use a daily price of <strong>${App.escapeHtml(form.daily_price.value)}</strong>`
            + (kwhBox.hidden ? '' : ` and charging price <strong>${App.escapeHtml(form.kwh_price.value)}</strong>/kWh`)
            + '.<br><small class="text-secondary">Ongoing rentals keep their old price.</small>';
    };

    // ---- Detail garasi (klik baris) ----
    const detailModal = new bootstrap.Modal('#detail-modal');
    const detailContent = document.getElementById('detail-content');
    document.querySelectorAll('tr.row-click').forEach((row) => {
        row.addEventListener('click', async (e) => {
            if (e.target.closest('input, a, button')) return; // klik checkbox tidak membuka detail
            detailContent.innerHTML = '<div class="p-5 text-center text-secondary"><div class="spinner-border spinner-border-sm me-2"></div>Loading details…</div>';
            detailModal.show();
            try {
                const res = await fetch(row.dataset.detailUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error(res.status);
                detailContent.innerHTML = await res.text();
            } catch (err) {
                detailContent.innerHTML = '<div class="p-5 text-center text-danger">Failed to load garage details.</div>';
            }
        });
    });

    refreshSelection();
})();
</script>
@endpush
