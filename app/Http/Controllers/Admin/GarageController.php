<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGarageBatchRequest;
use App\Http\Requests\UpdateGaragePriceRequest;
use App\Models\Garage;
use App\Services\GarageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller Admin: daftar garasi, detail pemakai, buat berkelompok, ubah harga & hapus massal.
 */
class GarageController extends Controller
{
    /** Pilihan jumlah baris per halaman tabel garasi. */
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public function __construct(private readonly GarageService $garages) {}

    /**
     * Daftar garasi dengan filter tipe (tombol) dan paginasi 10/25/50 baris.
     * Kartu ringkasan (total/tersedia/dipakai) mengikuti filter tipe yang aktif.
     */
    public function index(Request $request): View
    {
        $type = GarageType::tryFrom((string) $request->query('type'));
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE_OPTIONS, true) ? (int) $request->query('per_page') : 10;
        $scope = fn () => Garage::query()->when($type, fn ($q) => $q->where('type', $type));

        return view('admin.garages.index', [
            'garages' => $scope()->orderBy('type')->orderBy('sequence')->paginate($perPage)->withQueryString(),
            'summary' => [
                'total' => $scope()->count(),
                'available' => $scope()->where('status', GarageStatus::Available)->count(),
                'used' => $scope()->where('status', GarageStatus::Used)->count(),
            ],
            'garageTypes' => GarageType::cases(),
            'selectedType' => $type,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /** Isi pop-up detail garasi (diambil lewat fetch): sedang dipakai + riwayat. */
    public function show(Garage $garage): View
    {
        return view('admin.garages._detail', ['garage' => $garage, ...$this->garages->detail($garage)]);
    }

    /** Form buat garasi berkelompok (beserta nomor urut terakhir tiap tipe untuk pratinjau kode). */
    public function create(): View
    {
        return view('admin.garages.create', [
            'garageTypes' => GarageType::cases(),
            'lastSequences' => Garage::selectRaw('type, MAX(sequence) as last')->groupBy('type')->pluck('last', 'type'),
        ]);
    }

    /** Simpan garasi berkelompok dengan ID otomatis. */
    public function store(StoreGarageBatchRequest $request): RedirectResponse
    {
        $type = GarageType::from($request->validated('type'));
        $created = $this->garages->createBatch(
            $type,
            (int) $request->validated('count'),
            (float) $request->validated('daily_price'),
            $request->validated('kwh_price') !== null ? (float) $request->validated('kwh_price') : null,
        );

        return redirect()->route('admin.garages.index', ['type' => $type->value])
            ->with('success', "{$created} {$type->label()}(s) created.");
    }

    /** Ubah harga beberapa garasi satu tipe sekaligus. */
    public function updatePrices(UpdateGaragePriceRequest $request): RedirectResponse
    {
        $kwh = $request->validated('kwh_price');
        $updated = $this->garages->updatePrices(
            array_map('intval', $request->validated('ids')),
            (float) $request->validated('daily_price'),
            $kwh !== null ? (float) $kwh : null,
        );

        return back()->with('success', "Price of {$updated} garage(s) updated. The new price applies to future rentals.");
    }
}
