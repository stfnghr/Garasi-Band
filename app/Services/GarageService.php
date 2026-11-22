<?php

namespace App\Services;

use App\Enums\GarageStatus;
use App\Enums\GarageType;
use App\Models\Garage;
use App\Models\Rental;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika bisnis pengelolaan garasi.
 */
class GarageService
{
    public function __construct(private readonly GarageCodeGenerator $codeGenerator) {}

    /**
     * Buat sejumlah garasi sekaligus dengan ID berurutan melanjutkan nomor terakhir tipenya.
     *
     * @param  GarageType  $type  tipe garasi (S/C/E)
     * @param  int  $count  jumlah garasi yang dibuat (1..999)
     * @param  float  $dailyPrice  harga sewa harian
     * @param  float|null  $kwhPrice  harga isi listrik per kWh (hanya garasi mobil listrik)
     * @return int jumlah garasi yang dibuat
     *
     * @throws ValidationException bila kuota ID 999 terlampaui
     */
    public function createBatch(GarageType $type, int $count, float $dailyPrice, ?float $kwhPrice = null): int
    {
        return DB::transaction(function () use ($type, $count, $dailyPrice, $kwhPrice) {
            $sequences = $this->codeGenerator->sequencesAfter($this->codeGenerator->lastSequence($type), $count);
            $now = now();

            $rows = array_map(fn (int $seq) => [
                'code' => $this->codeGenerator->format($type, $seq),
                'type' => $type->value,
                'sequence' => $seq,
                'daily_price' => $dailyPrice,
                'kwh_price' => $type === GarageType::MobilListrik ? $kwhPrice : null,
                'status' => GarageStatus::Available->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $sequences);

            foreach (array_chunk($rows, 200) as $chunk) {
                Garage::insert($chunk);
            }

            return count($rows);
        });
    }

    /**
     * Garasi bertipe tertentu yang tidak sedang disewa hari ini.
     *
     * @return Collection<int, Garage>
     */
    public function availableToday(GarageType $type): Collection
    {
        return Garage::where('type', $type)->availableOn(today())->orderBy('sequence')->get();
    }

    /**
     * Ubah harga beberapa garasi sekaligus.
     *
     * Semua garasi yang dipilih harus satu tipe (Sepeda/Motor = S, Mobil = C, Mobil Listrik = E),
     * karena harga dan tarif kWh berbeda per tipe. Harga baru hanya berlaku untuk sewa BERIKUTNYA;
     * sewa yang sudah ada memakai harga snapshot di tabel rentals.
     *
     * @param  list<int>  $ids  id garasi yang dipilih
     * @param  float  $dailyPrice  harga sewa harian baru
     * @param  float|null  $kwhPrice  harga isi listrik per kWh baru (wajib bila tipe Mobil Listrik)
     * @return int jumlah garasi yang diperbarui
     *
     * @throws ValidationException bila tipe campuran atau kWh kosong untuk garasi listrik
     */
    public function updatePrices(array $ids, float $dailyPrice, ?float $kwhPrice): int
    {
        $type = $this->singleTypeOf($ids);

        if ($type === GarageType::MobilListrik && $kwhPrice === null) {
            throw ValidationException::withMessages(['kwh_price' => 'Charging price per kWh is required for EV garages.']);
        }

        return Garage::whereIn('id', $ids)->update([
            'daily_price' => $dailyPrice,
            'kwh_price' => $type === GarageType::MobilListrik ? $kwhPrice : null,
        ]);
    }

    /**
     * Detail sebuah garasi untuk pop-up admin: user yang sedang memakai (ongoing) dan riwayat pemakai sebelumnya.
     *
     * @return array{ongoing: Rental|null, history: Collection<int, Rental>}
     */
    public function detail(Garage $garage): array
    {
        $rentals = $garage->rentals()->with(['user' => fn ($q) => $q->withTrashed(), 'vehicle', 'payments'])
            ->orderByDesc('start_date')->get();
        $ongoing = $rentals->first(fn (Rental $r) => $r->isActiveToday());

        return [
            'ongoing' => $ongoing,
            'history' => $rentals->reject(fn (Rental $r) => $ongoing && $r->is($ongoing))->values(),
        ];
    }

    /**
     * Pastikan semua id garasi bertipe sama, lalu kembalikan tipenya.
     *
     * @throws ValidationException
     */
    private function singleTypeOf(array $ids): GarageType
    {
        $types = Garage::whereIn('id', $ids)->distinct()->pluck('type');

        if ($types->count() !== 1) {
            throw ValidationException::withMessages(['ids' => 'Select garages of the same type to change their price.']);
        }

        return $types->first();
    }

    /**
     * Sinkronkan kolom status dengan sewa yang aktif hari ini
     * (dijalankan harian oleh scheduler agar garasi yang masa sewanya habis kembali 'available').
     */
    public function syncStatuses(): void
    {
        $usedIds = Rental::activeOn(today())->pluck('garage_id');

        Garage::whereIn('id', $usedIds)->update(['status' => GarageStatus::Used]);
        Garage::whereNotIn('id', $usedIds)->update(['status' => GarageStatus::Available]);
    }
}
