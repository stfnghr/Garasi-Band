<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtendRentalRequest;
use App\Models\Rental;
use App\Services\RentalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Controller Admin: perpanjangan sewa garasi yang sedang disewa hari ini (UC07).
 * Sisa biaya selalu dihitung harian (BR-05).
 */
class RentalExtensionController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    public function index(): View
    {
        return view('admin.extensions.index', ['rentals' => $this->rentals->activeToday()]);
    }

    /** Form perpanjangan; bila tanggal baru diisi, tampilkan sisa biaya. */
    public function edit(Request $request, Rental $rental): View
    {
        $this->ensureActive($rental);
        $rental->load(['garage', 'user', 'vehicle', 'payments']);
        $cost = null;
        $extraDays = null;

        if ($request->filled('new_end_date')) {
            $request->validate(
                ['new_end_date' => ['date', 'after:'.$rental->end_date->toDateString()]],
                [], ['new_end_date' => 'new end date']
            );
            $newEnd = Carbon::parse($request->query('new_end_date'));
            $extraDays = (int) $rental->end_date->diffInDays($newEnd);
            $cost = $this->rentals->extensionCost($rental, $newEnd);
        }

        return view('admin.extensions.edit', compact('rental', 'cost', 'extraDays'));
    }

    public function update(ExtendRentalRequest $request, Rental $rental): RedirectResponse
    {
        $this->ensureActive($rental);
        $payment = $this->rentals->extendRental(
            $rental,
            Carbon::parse($request->validated('new_end_date')),
            $request->file('payment_proof'),
            $request->user(),
        );

        return redirect()->route('admin.extensions.index')->with(
            'success',
            "Rental of garage {$rental->garage->code} extended until {$payment->new_end_date->format('d-m-Y')}."
        );
    }

    private function ensureActive(Rental $rental): void
    {
        abort_unless(Rental::activeOn(today())->whereKey($rental->id)->exists(), 404, 'This rental is not active today.');
    }
}
