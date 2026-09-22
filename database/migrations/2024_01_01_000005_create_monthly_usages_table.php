<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->decimal('total_out', 10, 2)->default(0);
            $table->decimal('max_daily_out', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'year', 'month']);
            $table->index(['item_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_usages');
    }
};
