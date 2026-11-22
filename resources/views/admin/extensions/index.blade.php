{{-- Admin › Perpanjang Sewa (UC07): daftar garasi yang sedang disewa hari ini (end_date inklusif). --}}
@extends('layouts.app')
@section('title', 'Extensions')
@section('content')
<div class="mb-4">
    <h1 class="page-title">Rental Extensions</h1>
    <p class="page-subtitle">Garages rented today, {{ today()->format('j F Y') }}.</p>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Garage</th><th>User</th><th>Vehicle</th><th>Initial Billing</th><th>Period</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
            @forelse ($rentals as $rental)
                <tr class="type-bar-{{ $rental->garage->type->color() }}">
                    <td><span class="fw-bold">{{ $rental->garage->code }}</span>
                        <div><span class="badge-type type-{{ $rental->garage->type->color() }}">{{ $rental->garage->type->shortLabel() }}</span></div></td>
                    <td>{{ $rental->user->name }}<div class="small text-secondary">{{ $rental->user->email }}</div></td>
                    <td>{{ $rental->vehicle->brand_model }}<div class="small text-secondary">{{ $rental->vehicle->plate_number ?? $rental->vehicle->type->label() }}</div></td>
                    <td>{{ $rental->billingLabel() }}</td>
                    <td class="text-nowrap">{{ $rental->start_date->format('d-m-Y') }}<div class="small text-secondary">to {{ $rental->end_date->format('d-m-Y') }}</div></td>
                    <td class="text-end">@rupiah($rental->total_cost)</td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('admin.extensions.edit', $rental) }}"><i class="bi bi-calendar-plus me-1"></i>Extend</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i>No garage is rented today.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
