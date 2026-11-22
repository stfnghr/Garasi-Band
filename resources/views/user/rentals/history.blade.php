{{--
    User › Riwayat: semua sewa (aktif & selesai), kendaraan yang dititipkan, dan semua bukti bayar.
    Klik baris untuk melihat rincian (biaya, baterai, pembayaran).
--}}
@extends('layouts.app')
@section('title', 'Rental History')
@section('content')
<div class="mb-4">
    <h1 class="page-title">Rental History</h1>
    <p class="page-subtitle">Every garage you have rented, with its vehicle and payments.</p>
</div>

<div class="row g-3 mb-4">
    @foreach ([['Total Rentals', $stats['total'], 'bi-journal-text', 'bg-primary-subtle text-primary'],
               ['Active', $stats['active'], 'bi-p-circle', 'bg-success-subtle text-success'],
               ['Total Spent', 'Rp '.number_format($stats['spent'], 0, ',', '.'), 'bi-wallet2', 'bg-warning-subtle text-warning-emphasis']] as [$label, $value, $icon, $color])
        <div class="col-md-4"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon {{ $color }}"><i class="bi {{ $icon }}"></i></div>
            <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
        </div></div></div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Garage</th><th>Vehicle</th><th>Period</th><th>Billing</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($rentals as $rental)
                @php $active = $rental->isActiveToday(); @endphp
                <tr class="row-click" data-bs-toggle="collapse" data-bs-target="#detail-{{ $rental->id }}" aria-expanded="false">
                    <td><span class="fw-bold">{{ $rental->garage->code }}</span>
                        <div><span class="badge-type type-{{ $rental->garage->type->color() }}">{{ $rental->garage->type->shortLabel() }}</span></div></td>
                    <td>{{ $rental->vehicle->brand_model }}<div class="small text-secondary">{{ $rental->vehicle->type->label() }} {{ $rental->vehicle->plate_number ? '· '.$rental->vehicle->plate_number : '' }}</div></td>
                    <td class="text-nowrap">{{ $rental->start_date->format('d-m-Y') }}<div class="small text-secondary">to {{ $rental->end_date->format('d-m-Y') }}</div></td>
                    <td>{{ $rental->billingLabel() }}@if ($rental->payments->sum('extra_days'))<div class="small text-secondary">+ {{ $rental->payments->sum('extra_days') }} day(s) extension</div>@endif</td>
                    <td class="text-end fw-semibold">@rupiah($rental->total_cost)</td>
                    <td><span class="badge-status {{ $active ? 'status-available' : 'status-pending' }}">{{ $active ? 'Active' : ($rental->start_date->isFuture() ? 'Upcoming' : 'Finished') }}</span></td>
                    <td class="text-secondary"><i class="bi bi-chevron-down"></i></td>
                </tr>
                <tr class="collapse" id="detail-{{ $rental->id }}">
                    <td colspan="7" class="bg-light">
                        <div class="row g-3 small p-2">
                            <div class="col-md-4">
                                <div class="fw-semibold mb-1">Cost</div>
                                Rent @rupiah($rental->rent_cost) ({{ $rental->billingLabel() }}, daily @rupiah($rental->daily_price))<br>
                                Charging @rupiah($rental->charging_cost)
                                @if ($rental->remaining_battery_kwh !== null)
                                    <br>Battery on arrival {{ rtrim(rtrim($rental->remaining_battery_kwh, '0'), '.') }} / {{ rtrim(rtrim($rental->vehicle->battery_capacity_kwh, '0'), '.') }} kWh
                                @endif
                            </div>
                            <div class="col-md-8">
                                <div class="fw-semibold mb-1">Payments</div>
                                <ul class="mb-0 ps-3">
                                    @foreach ($rental->payments as $p)
                                        <li>{{ $p->paid_at->format('d-m-Y H:i') }} — {{ $p->type->label() }}{{ $p->extra_days ? " ({$p->extra_days} day(s), until {$p->new_end_date->format('d-m-Y')})" : '' }}
                                            — @rupiah($p->amount) — <a href="{{ asset('storage/'.$p->proof_path) }}" target="_blank" rel="noopener">view proof</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clock-history"></i>No rental history yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($rentals->hasPages())
        <div class="card-body border-top">{{ $rentals->links() }}</div>
    @endif
</div>
@endsection
