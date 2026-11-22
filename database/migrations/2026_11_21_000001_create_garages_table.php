<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garages', function (Blueprint $table) {
            $table->id();
            $table->char('code', 4)->unique()->comment('S001..S999, C001..C999, E001..E999');
            $table->enum('type', ['sepeda', 'mobil', 'mobil_listrik']);
            $table->unsignedSmallInteger('sequence')->comment('1..999 per tipe');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('kwh_price', 12, 2)->nullable()->comment('Hanya garasi mobil listrik');
            $table->enum('status', ['available', 'used'])->default('available');
            $table->timestamps();

            $table->unique(['type', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garages');
    }
};
