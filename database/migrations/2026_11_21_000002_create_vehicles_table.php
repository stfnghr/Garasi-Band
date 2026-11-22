<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['sepeda', 'motor', 'mobil', 'mobil_listrik']);
            $table->string('plate_number', 15)->nullable()->comment('Wajib kecuali sepeda');
            $table->string('brand_model', 100);
            $table->decimal('battery_capacity_kwh', 10, 2)->nullable()->comment('Kapasitas baterai penuh, wajib mobil listrik');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
