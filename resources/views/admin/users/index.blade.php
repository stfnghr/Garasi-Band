{{-- Admin › Verifikasi Pendaftar (UC06). Setiap Terima/Tolak meminta konfirmasi lewat pop-up. --}}
@extends('layouts.app')
@section('title', 'Verifications')
@section('content')
<div class="mb-4">
    <h1 class="page-title">New User Verification</h1>
    <p class="page-subtitle">Check that the NIK matches the ID card before approving an applicant.</p>
</div>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        Waiting for Verification <span class="badge-status status-pending">{{ $pending->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>NIK</th><th>Full Name</th><th>Phone</th><th>Email</th><th>Registered</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse ($pending as $applicant)
                <tr>
                    <td class="font-monospace fw-semibold">{{ $applicant->nik }}</td>
                    <td>{{ $applicant->name }}</td>
                    <td>{{ trim(chunk_split((string) $applicant->phone, 4, '-'), '-') }}</td>
                    <td>{{ $applicant->email }}</td>
                    <td>{{ $applicant->created_at->format('d-m-Y H:i') }}</td>
                    <td class="text-end text-nowrap">
                        <form class="d-inline" method="POST" action="{{ route('admin.users.approve', $applicant) }}"
                              data-confirm="Approve this applicant?" data-confirm-text="{{ $applicant->name }} (NIK {{ $applicant->nik }}) will be able to log in and rent garages." data-confirm-button="Yes, approve">
                            @csrf @method('PATCH')
                            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Approve</button>
                        </form>
                        <form class="d-inline" method="POST" action="{{ route('admin.users.reject', $applicant) }}"
                              data-confirm="Reject this applicant?" data-confirm-text="{{ $applicant->name }} will not be able to log in, and this email/NIK cannot be registered again." data-confirm-button="Yes, reject" data-confirm-icon="warning" data-confirm-danger>
                            @csrf @method('PATCH')
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Reject</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-check"></i>No applicants are waiting for verification.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Verification History</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>NIK</th><th>Name</th><th>Email</th><th>Status</th><th>Verified At</th></tr></thead>
            <tbody>
            @forelse ($users as $applicant)
                <tr>
                    <td class="font-monospace">{{ $applicant->nik }}</td>
                    <td>{{ $applicant->name }}</td>
                    <td>{{ $applicant->email }}</td>
                    <td><span class="badge-status status-{{ $applicant->status->value }}">{{ $applicant->status->label() }}</span></td>
                    <td>{{ $applicant->verified_at?->format('d-m-Y H:i') ?? '–' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i>Nothing yet.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
