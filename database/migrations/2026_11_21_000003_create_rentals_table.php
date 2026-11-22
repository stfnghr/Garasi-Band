<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel transaksi sewa. Periode sewa inklusif: start_date <= hari <= end_date.
     */
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('garage_id')->constrained()->restrictOnDelete();
            $table->date('start_date')->comment('Selalu tanggal hari ini (Day One)');
            $table->date('end_date')->comment('Inklusif: start_date + (hari - 1)');
            $table->unsignedSmallInteger('total_days')->comment('Durasi sewa awal');
            $table->unsignedTinyInteger('days_in_month')->comment('Ambang bulanan = daysInMonth bulan start_date');
            $table->unsignedSmallInteger('billed_months');
            $table->unsignedTinyInteger('billed_days');
            $table->decimal('daily_price', 12, 2)->comment('Snapshot harga harian');
            $table->decimal('kwh_price', 12, 2)->nullable()->comment('Snapshot harga per kWh');
            $table->decimal('remaining_battery_kwh', 10, 2)->nullable()->comment('Sisa baterai saat masuk');
            $table->decimal('rent_cost', 12, 2);
            $table->decimal('charging_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->comment('Termasuk semua perpanjangan');
            $table->timestamps();

            $table->index(['garage_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
