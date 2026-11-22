{{-- User › Sewa Aktif: garasi yang sedang disewa hari ini, ditampilkan sebagai kartu. --}}
@extends('layouts.app')
@section('title', 'Active Rentals')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="page-title">Active Rentals</h1>
        <p class="page-subtitle">Garages you are renting today.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('user.rentals.create') }}"><i class="bi bi-plus-lg me-1"></i>New Rental</a>
</div>

@if ($rentals->isEmpty())
    <div class="card"><div class="empty-state">
        <i class="bi bi-p-circle"></i>
        <div class="mb-3">You are not renting any garage right now.</div>
        <a class="btn btn-primary btn-sm" href="{{ route('user.rentals.create') }}">Rent now</a>
    </div></div>
@else
    <div class="row g-3">
        @foreach ($rentals as $rental)
            @php
                // Sisa hari termasuk hari ini (end_date inklusif).
                $remaining = (int) today()->diffInDays($rental->end_date) + 1;
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 type-bar-{{ $rental->garage->type->color() }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fs-4 fw-bold">{{ $rental->garage->code }}</div>
                                <span class="badge-type type-{{ $rental->garage->type->color() }}"><i class="bi {{ $rental->garage->type->icon() }} me-1"></i>{{ $rental->garage->type->label() }}</span>
                            </div>
                            <span class="badge-status status-available">{{ $remaining }} day(s) left</span>
                        </div>
                        <div class="small text-secondary mb-3">{{ $rental->vehicle->type->label() }} · {{ $rental->vehicle->brand_model }} {{ $rental->vehicle->plate_number }}</div>
                        <div class="row small g-2">
                            <div class="col-6"><div class="text-secondary">Start</div><strong>{{ $rental->start_date->format('d-m-Y') }}</strong></div>
                            <div class="col-6"><div class="text-secondary">End</div><strong>{{ $rental->end_date->format('d-m-Y') }}</strong></div>
                            <div class="col-6"><div class="text-secondary">Billing</div><strong>{{ $rental->billingLabel() }}</strong></div>
                            <div class="col-6"><div class="text-secondary">Total</div><strong>@rupiah($rental->total_cost)</strong></div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent small">
                        <i class="bi bi-receipt me-1"></i>Proof:
                        @foreach ($rental->payments as $payment)
                            <a href="{{ asset('storage/'.$payment->proof_path) }}" target="_blank" rel="noopener">{{ $payment->type->label() }}{{ $payment->extra_days ? " +{$payment->extra_days} day(s)" : '' }}</a>@if (! $loop->last), @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
