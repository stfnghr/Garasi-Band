<?php

namespace App\Services;

use App\Enums\GarageType;
use App\Enums\PaymentType;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Laporan sewa garasi berdasarkan pembayaran yang masuk (sewa baru + perpanjangan).
 *
 * Semua laporan memakai filter yang sama: rentang tanggal pembayaran (paid_at) dan tipe garasi (opsional).
 * Empat laporan wajib dari soal tetap tersedia:
 * - per bulan dalam tahun  (filter "Tahun ini" => 12 baris bulan),
 * - per hari dalam bulan   (filter "Bulan ini" => satu baris per tanggal),
 * - per tipe garasi,
 * - per user.
 */
class ReportService
{
    /** Batas rentang laporan agar tabel per hari tidak terlalu panjang. */
    public const MAX_RANGE_DAYS = 1830;

    /**
     * Susun seluruh data laporan untuk rentang & tipe garasi tertentu.
     *
     * @param  CarbonInterface  $from  tanggal awal (inklusif)
     * @param  CarbonInterface  $to  tanggal akhir (inklusif)
     * @param  GarageType|null  $type  null = semua tipe
     * @return array{
     *     summary: array{total: float, sewa_baru: int, perpanjangan: int, rent: float, charging: float, extension: float},
     *     monthly: Collection, daily: Collection, by_type: Collection, by_user: Collection, by_garage: Collection,
     *     details: Collection<int, Payment>
     * }
     */
    public function build(CarbonInterface $from, CarbonInterface $to, ?GarageType $type = null): array
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->endOfDay();

        // Ambil pembayaran dalam rentang beserta relasi yang dibutuhkan (eager loading, hindari N+1 query).
        $payments = Payment::query()
            ->with(['rental.garage', 'rental.vehicle', 'rental.user' => fn ($q) => $q->withTrashed()])
            ->whereBetween('paid_at', [$from, $to])
            ->when($type, fn ($q) => $q->whereHas('rental.garage', fn ($g) => $g->where('type', $type)))
            ->orderByDesc('paid_at')
            ->get();

        return [
            'summary' => $this->summary($payments),
            'monthly' => $this->monthly($payments, $from, $to),
            'daily' => $this->daily($payments, $from, $to),
            'by_type' => $this->byGarageType($payments, $type),
            'by_user' => $this->byUser($payments),
            'by_garage' => $this->byGarage($payments),
            'details' => $payments,
        ];
    }

    /** Kartu ringkasan: total pendapatan & komposisinya. */
    private function summary(Collection $payments): array
    {
        $newRentals = $payments->where('type', PaymentType::SewaBaru);
        $charging = $newRentals->sum(fn (Payment $p) => (float) $p->rental->charging_cost);
        $extension = $payments->where('type', PaymentType::Perpanjangan)->sum(fn (Payment $p) => (float) $p->amount);
        $total = $payments->sum(fn (Payment $p) => (float) $p->amount);

        return [
            'total' => round($total, 2),
            'sewa_baru' => $newRentals->count(),
            'perpanjangan' => $payments->where('type', PaymentType::Perpanjangan)->count(),
            'rent' => round($total - $charging - $extension, 2),
            'charging' => round($charging, 2),
            'extension' => round($extension, 2),
        ];
    }

    /** Per bulan: setiap bulan di dalam rentang (bulan kosong bernilai 0). */
    private function monthly(Collection $payments, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $grouped = $payments->groupBy(fn (Payment $p) => $p->paid_at->format('Y-m'));

        return collect(CarbonPeriod::create($from->startOfMonth(), '1 month', $to->startOfMonth()))
            ->map(fn ($month) => [
                'label' => $month->format('F Y'),
                ...$this->summarize($grouped->get($month->format('Y-m'), collect())),
            ])->values();
    }

    /** Per hari: setiap tanggal di dalam rentang (hari kosong bernilai 0). */
    private function daily(Collection $payments, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $grouped = $payments->groupBy(fn (Payment $p) => $p->paid_at->toDateString());

        return collect(CarbonPeriod::create($from->startOfDay(), $to->startOfDay()))
            ->map(fn ($day) => [
                'label' => $day->format('d-m-Y'),
                ...$this->summarize($grouped->get($day->toDateString(), collect())),
            ])->values();
    }

    /** Per tipe garasi (hanya tipe terpilih bila difilter). */
    private function byGarageType(Collection $payments, ?GarageType $filter): Collection
    {
        $grouped = $payments->groupBy(fn (Payment $p) => $p->rental->garage->type->value);

        return collect($filter ? [$filter] : GarageType::cases())->map(fn (GarageType $t) => [
            'key' => $t->value,
            'label' => $t->label(),
            ...$this->summarize($grouped->get($t->value, collect())),
        ]);
    }

    /** Per user, diurutkan dari pendapatan terbesar. */
    private function byUser(Collection $payments): Collection
    {
        return $payments->groupBy(fn (Payment $p) => $p->rental->user_id)
            ->map(function (Collection $group) {
                $user = $group->first()->rental->user;

                return [
                    'label' => $user->name.($user->trashed() ? ' (deleted account)' : ' ('.$user->email.')'),
                    ...$this->summarize($group),
                ];
            })
            ->sortByDesc('total')->values();
    }

    /** Per garasi (kode), diurutkan dari pendapatan terbesar. */
    private function byGarage(Collection $payments): Collection
    {
        return $payments->groupBy(fn (Payment $p) => $p->rental->garage->code)
            ->map(fn (Collection $group, string $code) => [
                'label' => $code,
                'type' => $group->first()->rental->garage->type->value,
                ...$this->summarize($group),
            ])
            ->sortByDesc('total')->values();
    }

    /** @return array{sewa_baru: int, perpanjangan: int, total: float} */
    private function summarize(Collection $payments): array
    {
        return [
            'sewa_baru' => $payments->where('type', PaymentType::SewaBaru)->count(),
            'perpanjangan' => $payments->where('type', PaymentType::Perpanjangan)->count(),
            'total' => round($payments->sum(fn (Payment $p) => (float) $p->amount), 2),
        ];
    }
}
