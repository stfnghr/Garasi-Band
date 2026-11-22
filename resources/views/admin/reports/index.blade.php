{{--
    Admin › Laporan Sewa (UC08).
    Filter (otomatis diterapkan saat berubah, tanpa tombol "Terapkan"):
    - Preset: Hari ini / Minggu ini / Bulan ini / Tahun ini.
    - Rentang tanggal bebas (flatpickr mode range) → query ?dari=YYYY-MM-DD&sampai=YYYY-MM-DD.
    - Tipe garasi (semua / S / C / E) → query ?tipe=...
    Isi: kartu ringkasan, grafik (pendapatan per waktu, per tipe, per garasi, komposisi),
    tabel per bulan, per hari, per tipe, per user, dan riwayat detail pembayaran (paginasi).
--}}
@extends('layouts.app')
@section('title', 'Reports')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
@endpush
@php
    // Rentang preset dihitung di server agar sama persis dengan zona waktu aplikasi (Asia/Jakarta).
    $presets = [
        'Today' => [today(), today()],
        'This week' => [today()->startOfWeek(), today()->endOfWeek()],
        'This month' => [today()->startOfMonth(), today()->endOfMonth()],
        'This year' => [today()->startOfYear(), today()->endOfYear()],
    ];
    $isActive = fn ($r) => $from->isSameDay($r[0]) && $to->isSameDay($r[1]);
    // Grafik waktu: per hari bila rentang ≤ 62 hari, selain itu per bulan.
    $useDaily = $from->diffInDays($to) <= 62;
    $timeSeries = $useDaily ? $daily : $monthly;
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
@endphp
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="page-title">Rental Reports</h1>
        <p class="page-subtitle">Period {{ $from->format('j F Y') }} – {{ $to->format('j F Y') }}{{ $type ? ' · '.$type->label() : ' · All garage types' }}</p>
    </div>
</div>

{{-- ===== FILTER ===== --}}
<form id="report-filter" method="GET" action="{{ route('admin.reports.index') }}" class="card mb-4">
    <div class="card-body row g-3 align-items-end">
        <div class="col-lg-5">
            <label class="form-label">Quick range</label>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($presets as $label => $range)
                    <button type="button" class="btn btn-sm {{ $isActive($range) ? 'btn-primary' : 'btn-outline-primary' }}"
                            data-preset-from="{{ $range[0]->toDateString() }}" data-preset-to="{{ $range[1]->toDateString() }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="range">Date Range</label>
            <input class="form-control" id="range" placeholder="Pick start & end dates">
            <input type="hidden" name="from" id="dari" value="{{ $from->toDateString() }}">
            <input type="hidden" name="to" id="sampai" value="{{ $to->toDateString() }}">
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="tipe">Garage Type</label>
            <select class="form-select" id="tipe" name="type">
                <option value="">All types</option>
                @foreach ($garageTypes as $t)
                    <option value="{{ $t->value }}" @selected($type === $t)>{{ $t->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
</form>

{{-- ===== RINGKASAN ===== --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value">{{ $rp($summary['total']) }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">New Rentals</div><div class="stat-value">{{ $summary['sewa_baru'] }}</div><div class="small text-secondary">{{ $rp($summary['rent'] + $summary['charging']) }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Extensions</div><div class="stat-value">{{ $summary['perpanjangan'] }}</div><div class="small text-secondary">{{ $rp($summary['extension']) }}</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Charging</div><div class="stat-value">{{ $rp($summary['charging']) }}</div></div></div>
</div>

{{-- ===== GRAFIK ===== --}}
<div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">Revenue {{ $useDaily ? 'per Day' : 'per Month' }}</div><div class="card-body"><div class="chart-box"><canvas id="chart-time"></canvas></div></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Revenue by Garage Type</div><div class="card-body"><div class="chart-box"><canvas id="chart-type"></canvas></div></div></div></div>
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">Top 10 Garages by Revenue</div><div class="card-body"><div class="chart-box"><canvas id="chart-garage"></canvas></div></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Revenue Mix</div><div class="card-body"><div class="chart-box"><canvas id="chart-mix"></canvas></div></div></div></div>
</div>

{{-- ===== TABEL RINGKASAN ===== --}}
@php
    // Satu partial kecil (closure) untuk 4 tabel ringkasan yang strukturnya sama: label | sewa baru | perpanjangan | pendapatan.
    $table = function (string $title, $rows, string $labelHead, bool $scroll = false) use ($rp) {
        return view('admin.reports._summary_table', compact('title', 'rows', 'labelHead', 'scroll', 'rp'))->render();
    };
@endphp
<div class="row g-4 mb-4">
    <div class="col-lg-6">{!! $table('Per Month', $monthly, 'Month', true) !!}</div>
    <div class="col-lg-6">{!! $table('Per Day', $daily, 'Date', true) !!}</div>
    <div class="col-lg-6">{!! $table('Per Garage Type', $by_type, 'Type') !!}</div>
    <div class="col-lg-6">{!! $table('Per User', $by_user, 'User', true) !!}</div>
</div>

{{-- ===== RIWAYAT DETAIL ===== --}}
<div class="card">
    <div class="card-header d-flex justify-content-between"><span>Payment Details</span><span class="text-secondary small">{{ $details->total() }} transaction(s)</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Paid At</th><th>Type</th><th>Garage</th><th>User</th><th>Vehicle</th><th>Notes</th><th class="text-end">Amount</th><th>Proof</th></tr></thead>
            <tbody>
            @forelse ($details as $payment)
                @php($r = $payment->rental)
                <tr class="type-bar-{{ $r->garage->type->color() }}">
                    <td class="text-nowrap">{{ $payment->paid_at->format('d-m-Y H:i') }}</td>
                    <td><span class="badge-status {{ $payment->type === \App\Enums\PaymentType::SewaBaru ? 'status-approved' : 'status-pending' }}">{{ $payment->type->label() }}</span></td>
                    <td><span class="fw-semibold">{{ $r->garage->code }}</span> <span class="badge-type type-{{ $r->garage->type->color() }}">{{ $r->garage->type->shortLabel() }}</span></td>
                    <td>{{ $r->user->name }}@if ($r->user->trashed())<div class="small text-secondary">deleted account</div>@endif</td>
                    <td>{{ $r->vehicle->brand_model }}<div class="small text-secondary">{{ $r->vehicle->plate_number ?? $r->vehicle->type->label() }}</div></td>
                    <td class="small">
                        @if ($payment->type === \App\Enums\PaymentType::SewaBaru)
                            {{ $r->billingLabel() }}@if ((float) $r->charging_cost > 0) · charging {{ $rp($r->charging_cost) }}@endif
                        @else
                            +{{ $payment->extra_days }} day(s) ({{ $payment->previous_end_date?->format('d-m-Y') }} → {{ $payment->new_end_date?->format('d-m-Y') }})
                        @endif
                    </td>
                    <td class="text-end text-nowrap fw-semibold">{{ $rp($payment->amount) }}</td>
                    <td><a href="{{ asset('storage/'.$payment->proof_path) }}" target="_blank" rel="noopener" class="btn btn-light btn-sm border"><i class="bi bi-image"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-graph-down"></i>No transactions for this filter.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($details->hasPages())<div class="card-footer bg-transparent">{{ $details->links() }}</div>@endif
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
(() => {
    const form = document.getElementById('report-filter');
    const dari = document.getElementById('dari');
    const sampai = document.getElementById('sampai');
    // Kirim filter otomatis; halaman paginasi tidak ikut (kembali ke halaman 1).
    const submit = () => form.submit();

    // Rentang bebas: submit setelah tanggal awal & akhir sama-sama dipilih.
    flatpickr('#range', {
        mode: 'range', dateFormat: 'Y-m-d', altInput: true, altFormat: 'j M Y', altInputClass: 'form-control',
        defaultDate: [dari.value, sampai.value],
        onClose: (dates, _str, fp) => {
            if (dates.length !== 2) return;
            dari.value = fp.formatDate(dates[0], 'Y-m-d');
            sampai.value = fp.formatDate(dates[1], 'Y-m-d');
            submit();
        },
    });
    // Tombol preset & dropdown tipe.
    document.querySelectorAll('[data-preset-from]').forEach((btn) => btn.addEventListener('click', () => {
        dari.value = btn.dataset.presetFrom;
        sampai.value = btn.dataset.presetTo;
        submit();
    }));
    document.getElementById('tipe').addEventListener('change', submit);

    // ===== Grafik (Chart.js) =====
    const rupiah = (v) => 'Rp ' + Math.round(v).toLocaleString('id-ID');
    const money = { callbacks: { label: (c) => `${c.dataset.label ?? c.label}: ${rupiah(c.parsed.y ?? c.parsed)}` } };
    const typeColor = @json(collect($garageTypes)->mapWithKeys(fn ($t) => [$t->value => $t->hex()]));
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

    const time = @json($timeSeries->map(fn ($r) => ['label' => $r['label'], 'total' => $r['total']]));
    new Chart(document.getElementById('chart-time'), {
        type: 'line',
        data: { labels: time.map((r) => r.label), datasets: [{ label: 'Revenue', data: time.map((r) => r.total), borderColor: '#6d4bd8', backgroundColor: 'rgba(109,75,216,.12)', fill: true, tension: .3, pointRadius: 3 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: money }, scales: { y: { beginAtZero: true, ticks: { callback: rupiah } } } },
    });

    const byType = @json($by_type->values());
    new Chart(document.getElementById('chart-type'), {
        type: 'pie',
        data: { labels: byType.map((r) => r.label), datasets: [{ data: byType.map((r) => r.total), backgroundColor: byType.map((r) => typeColor[r.key]) }] },
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: money } },
    });

    const byGarage = @json($by_garage->take(10)->values());
    new Chart(document.getElementById('chart-garage'), {
        type: 'bar',
        data: { labels: byGarage.map((r) => r.label), datasets: [{ label: 'Revenue', data: byGarage.map((r) => r.total), backgroundColor: byGarage.map((r) => typeColor[r.type]), borderRadius: 6 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: money }, scales: { y: { beginAtZero: true, ticks: { callback: rupiah } } } },
    });

    const mix = @json([$summary['rent'], $summary['charging'], $summary['extension']]);
    new Chart(document.getElementById('chart-mix'), {
        type: 'doughnut',
        data: { labels: ['Rent', 'Charging', 'Extensions'], datasets: [{ data: mix, backgroundColor: ['#6d4bd8', '#e8930c', '#c2416b'] }] },
        options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: money } },
    });
})();
</script>
@endpush
