<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * category_defaults menyimpan parameter statis per kategori barang.
     * Digunakan sebagai fallback (STATIC_CATEGORY) saat SKU masih baru
     * (umur < 30 hari) atau saat Python FastAPI tidak tersedia.
     *
     * Kolom:
     *   avg_daily_demand : rata-rata kebutuhan harian representatif untuk kategori ini
     *   safety_days      : jumlah hari safety stock statis
     *   source           : apakah nilai dihitung dari histori atau diisi manual
     */
    public function up(): void
    {
        Schema::create('category_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->unique()
                  ->constrained('categories')
                  ->cascadeOnDelete();

            $table->decimal('avg_daily_demand', 12, 3)->default(0)
                  ->comment('Rata-rata demand harian representatif untuk kategori');
            $table->unsignedSmallInteger('safety_days')->default(7)
                  ->comment('Hari safety stock statis untuk SKU baru/fallback');
            $table->enum('source', ['manual', 'historical'])->default('manual')
                  ->comment('Asal nilai: manual=diisi operator, historical=dihitung dari histori');

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_defaults');
    }
};
