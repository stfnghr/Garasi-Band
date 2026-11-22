<?php

namespace App\Http\Controllers;

use App\Enums\GarageType;
use App\Enums\VehicleType;
use App\Http\Requests\StoreRentalRequest;
use App\Models\Rental;
use App\Services\GarageService;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Controller fitur User: sewa aktif, riwayat sewa, dan sewa garasi baru.
 */
class RentalController extends Controller
{
    public function __construct(
        private readonly RentalService $rentals,
        private readonly GarageService $garages,
    ) {}

    /** Sewa yang sedang aktif hari ini (UC04). */
    public function index(Request $request): View
    {
        $rentals = $request->user()->rentals()->activeOn(today())
            ->with(['garage', 'vehicle', 'payments'])->orderBy('end_date')->get();

        return view('user.rentals.index', compact('rentals'));
    }

    /**
     * Riwayat semua sewa milik user (aktif & selesai) beserta kendaraan dan bukti bayarnya,
     * sehingga user tahu kendaraan apa saja yang pernah dititipkan di garasi mana.
     */
    public function history(Request $request): View
    {
        $rentals = $request->user()->rentals()
            ->with(['garage', 'vehicle', 'payments'])
            ->orderByDesc('start_date')->orderByDesc('id')
            ->paginate(10);

        $stats = [
            'total' => $request->user()->rentals()->count(),
            'active' => $request->user()->rentals()->activeOn(today())->count(),
            'spent' => (float) $request->user()->rentals()->sum('total_cost'),
        ];

        return view('user.rentals.history', compact('rentals', 'stats'));
    }

    /**
     * Form sewa baru. Bila kriteria sudah diisi (langkah "Cari Garasi"),
     * tampilkan garasi tersedia hari ini beserta rincian & total biaya per garasi.
     */
    public function create(Request $request): View
    {
        $options = collect();
        $criteria = null;

        if ($request->has('vehicle_type')) {
            $validator = Validator::make(
                StoreRentalRequest::normalize($request->query()),
                StoreRentalRequest::criteriaRules(),
                [],
                StoreRentalRequest::attributeNames(),
            );
            $validator->after(fn ($v) => StoreRentalRequest::checkGarageType($v));
            $validator->after(fn ($v) => StoreRentalRequest::checkRentalType($v));
            $criteria = $validator->validate();

            $options = $this->garages->availableToday(GarageType::from($criteria['garage_type']))
                ->map(fn ($garage) => ['garage' => $garage, 'quote' => $this->rentals->quote($criteria, $garage)]);
        }

        return view('user.rentals.create', [
            'criteria' => $criteria,
            'options' => $options,
            'vehicleTypes' => VehicleType::cases(),
            'garageTypes' => GarageType::cases(),
        ]);
    }

    /** Simpan sewa baru beserta bukti pelunasan (UC03). */
    public function store(StoreRentalRequest $request): RedirectResponse
    {
        /** @var Rental $rental */
        $rental = $this->rentals->createRental($request->user(), $request->validated(), $request->file('payment_proof'));

        return redirect()->route('user.rentals.index')
            ->with('success', "Rental of garage {$rental->garage->code} saved.");
    }
}
