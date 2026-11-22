<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Controller Admin: laporan sewa garasi dengan filter rentang tanggal & tipe garasi (UC08).
 */
class ReportController extends Controller
{
    /** Jumlah baris riwayat detail per halaman. */
    private const DETAIL_PER_PAGE = 15;

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        // Validasi filter. Default: bulan ini, semua tipe.
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'in:'.implode(',', array_column(GarageType::cases(), 'value'))],
        ], [], ['from' => 'start date', 'to' => 'end date', 'type' => 'garage type']);

        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : now()->startOfMonth();
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : now()->endOfMonth();
        if ($from->diffInDays($to) > ReportService::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(ReportService::MAX_RANGE_DAYS);
        }
        $type = GarageType::tryFrom((string) ($filters['type'] ?? ''));

        $report = $this->reports->build($from, $to, $type);

        // Riwayat detail dipaginasi agar halaman tetap ringan.
        $page = LengthAwarePaginator::resolveCurrentPage();
        $details = new LengthAwarePaginator(
            $report['details']->forPage($page, self::DETAIL_PER_PAGE)->values(),
            $report['details']->count(),
            self::DETAIL_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page'],
        );

        return view('admin.reports.index', [
            ...$report,
            'details' => $details,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'garageTypes' => GarageType::cases(),
        ]);
    }
}
