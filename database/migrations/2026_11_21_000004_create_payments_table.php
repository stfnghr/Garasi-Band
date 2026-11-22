<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['sewa_baru', 'perpanjangan']);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('extra_days')->nullable()->comment('Tambahan hari (perpanjangan)');
            $table->date('previous_end_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->string('proof_path');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
