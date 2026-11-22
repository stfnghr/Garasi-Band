{{-- Tabel ringkasan laporan (dipakai untuk per bulan / per hari / per tipe / per user). --}}
<div class="card h-100">
    <div class="card-header">{{ $title }}</div>
    <div class="table-responsive {{ $scroll ? 'scroll-table' : '' }}">
        <table class="table table-sm align-middle mb-0">
            <thead class="sticky-top"><tr><th>{{ $labelHead }}</th><th class="text-end">New Rentals</th><th class="text-end">Extensions</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $row['total'] > 0 ? '' : 'text-secondary' }}">
                    <td>{{ $row['label'] }}</td>
                    <td class="text-end">{{ $row['sewa_baru'] }}</td>
                    <td class="text-end">{{ $row['perpanjangan'] }}</td>
                    <td class="text-end fw-semibold">{{ $rp($row['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-secondary py-3">No data yet.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end">{{ collect($rows)->sum('sewa_baru') }}</td><td class="text-end">{{ collect($rows)->sum('perpanjangan') }}</td><td class="text-end">{{ $rp(collect($rows)->sum('total')) }}</td></tr></tfoot>
        </table>
    </div>
</div>
