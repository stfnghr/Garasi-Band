{{--
    Isi pop-up detail garasi (dimuat lewat fetch dari halaman daftar garasi).
    Tab "Sedang Dipakai": user & kendaraan yang menempati garasi hari ini (kosong bila tersedia).
    Tab "Riwayat": user sebelumnya yang pernah memakai garasi ini.
--}}
@php $vehicleText = fn ($v) => $v->type->label().' · '.$v->brand_model.($v->plate_number ? ' · '.$v->plate_number : ''); @endphp
<div class="modal-header">
    <div>
        <h5 class="modal-title mb-1">Garage {{ $garage->code }}</h5>
        <span class="badge-type type-{{ $garage->type->color() }}"><i class="bi {{ $garage->type->icon() }} me-1"></i>{{ $garage->type->label() }}</span>
        <span class="badge-status status-{{ $garage->status->value }} ms-1">{{ $ongoing ? 'In use' : 'Available' }}</span>
        <span class="small text-secondary ms-2">Daily @rupiah($garage->daily_price){{ $garage->kwh_price ? ' · Charging Rp '.number_format($garage->kwh_price, 0, ',', '.').'/kWh' : '' }}</span>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-ongoing" type="button">Ongoing</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-history" type="button">History ({{ $history->count() }})</button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-ongoing">
            @if ($ongoing)
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary fw-semibold mb-2"><i class="bi bi-person me-1"></i>USER</div>
                            <div class="fw-bold">{{ $ongoing->user->name }}{{ $ongoing->user->trashed() ? ' (deleted account)' : '' }}</div>
                            <div class="small">{{ $ongoing->user->email }}</div>
                            <div class="small">Phone: {{ $ongoing->user->phone ? trim(chunk_split($ongoing->user->phone, 4, '-'), '-') : '–' }}</div>
                            <div class="small font-monospace">NIK: {{ $ongoing->user->nik ?? '–' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-secondary fw-semibold mb-2"><i class="bi bi-car-front me-1"></i>VEHICLE</div>
                            <div class="fw-bold">{{ $ongoing->vehicle->brand_model }}</div>
                            <div class="small">{{ $ongoing->vehicle->type->label() }}</div>
                            <div class="small">Plate: {{ $ongoing->vehicle->plate_number ?? '–' }}</div>
                            @if ($ongoing->vehicle->battery_capacity_kwh)
                                <div class="small">Battery: {{ rtrim(rtrim($ongoing->remaining_battery_kwh, '0'), '.') }} / {{ rtrim(rtrim($ongoing->vehicle->battery_capacity_kwh, '0'), '.') }} kWh on arrival</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <div class="row small g-2">
                                <div class="col-sm-3"><div class="text-secondary">Start</div><strong>{{ $ongoing->start_date->format('d-m-Y') }}</strong></div>
                                <div class="col-sm-3"><div class="text-secondary">End</div><strong>{{ $ongoing->end_date->format('d-m-Y') }}</strong></div>
                                <div class="col-sm-3"><div class="text-secondary">Billing</div><strong>{{ $ongoing->billingLabel() }}</strong></div>
                                <div class="col-sm-3"><div class="text-secondary">Total</div><strong>@rupiah($ongoing->total_cost)</strong></div>
                            </div>
                            <div class="small mt-2">Payment proof:
                                @foreach ($ongoing->payments as $p)
                                    <a href="{{ asset('storage/'.$p->proof_path) }}" target="_blank" rel="noopener">{{ $p->type->label() }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="empty-state"><i class="bi bi-p-circle"></i>This garage is empty, nobody is using it today.</div>
            @endif
        </div>
        <div class="tab-pane fade" id="tab-history">
            @if ($history->isEmpty())
                <div class="empty-state"><i class="bi bi-clock-history"></i>No usage history yet.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Period</th><th>User</th><th>Vehicle</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @foreach ($history as $r)
                            <tr>
                                <td class="text-nowrap">{{ $r->start_date->format('d-m-Y') }} – {{ $r->end_date->format('d-m-Y') }}</td>
                                <td>{{ $r->user->name }}<div class="small text-secondary">{{ $r->user->trashed() ? 'deleted account' : $r->user->email }}</div></td>
                                <td>{{ $vehicleText($r->vehicle) }}</td>
                                <td class="text-end">@rupiah($r->total_cost)</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
