<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * item_classifications — Hasil klasifikasi ABC-XYZ dan pola permintaan per SKU.
     *
     * Diisi oleh Python FastAPI (POST /api/v1/classification/batch), mingguan.
     * Kunci unik (item_id) memastikan hanya satu record aktif per SKU (upsert).
     *
     * Klasifikasi ABC (berdasarkan annual_value = total demand_clean 365 hari × unit_cost):
     *   A = 0-80% nilai kumulatif, B = 80-95%, C = 95-100%
     *
     * Klasifikasi XYZ (berdasarkan CV = std/mean demand per bulan):
     *   X: CV < 0.5, Y: 0.5 ≤ CV < 1.0, Z: CV ≥ 1.0
     *
     * Pola permintaan Syntetos-Boylan-Croston:
     *   ADI  = jumlah periode / jumlah periode dengan demand > 0
     *   CV²  = (std/mean)² dihitung HANYA pada nilai demand non-nol
     *   smooth       : ADI < 1.32 AND CV² < 0.49
     *   intermittent : ADI ≥ 1.32 AND CV² < 0.49
     *   erratic      : ADI < 1.32 AND CV² ≥ 0.49
     *   lumpy        : ADI ≥ 1.32 AND CV² ≥ 0.49
     *
     * Kepercayaan: hanya jika histori ≥ 30 hari.
     * Python menulis; Laravel membaca (routing model forecast di Tahap 3).
     */
    public function up(): void
    {
        Schema::create('item_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                  ->unique()
                  ->constrained('items')
                  ->cascadeOnDelete()
                  ->comment('Satu record aktif per SKU. Upsert saat run mingguan.');

            $table->timestamp('computed_at')->useCurrent()
                  ->comment('Waktu terakhir klasifikasi dihitung');

            // ── Klasifikasi ABC ────────────────────────────────────────────────
            $table->enum('abc_class', ['A', 'B', 'C'])->default('C');
            $table->decimal('annual_value', 18, 4)->default(0)
                  ->comment('total demand_clean 365 hari × unit_cost');

            // ── Klasifikasi XYZ ────────────────────────────────────────────────
            $table->enum('xyz_class', ['X', 'Y', 'Z'])->default('Z');

            // ── Pola Permintaan (Syntetos-Boylan) ──────────────────────────────
            $table->decimal('adi', 8, 4)->nullable()
                  ->comment('Average Demand Interval = periode / periode-dengan-demand');
            $table->decimal('cv2', 8, 4)->nullable()
                  ->comment('CV² dari demand non-nol. Threshold: 0.49');
            $table->enum('demand_pattern', [
                'smooth',
                'intermittent',
                'erratic',
                'lumpy',
            ])->default('smooth')
              ->comment('Menentukan routing model forecast di Tahap 3');

            // ── Metadata ───────────────────────────────────────────────────────
            $table->unsignedSmallInteger('history_days')->default(0)
                  ->comment('Jumlah hari histori yang dipakai. < 30 = klasifikasi tidak dipercaya.');
            $table->boolean('is_reliable')->default(false)
                  ->comment('TRUE jika history_days >= 30');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_classifications');
    }
};
