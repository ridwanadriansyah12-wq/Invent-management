<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * forecast_runs — Riwayat setiap run forecasting per SKU.
     *
     * Diisi oleh Python FastAPI setiap run harian. Bersifat append-only (audit trail).
     * Laravel membaca baris dengan status='SUCCESS' dan run_at terbaru untuk Tahap 4.
     *
     * Model yang mungkin muncul di model_used:
     *   LIGHTGBM_GLOBAL, MOVING_AVERAGE_28D, AUTO_ETS,  (untuk smooth/erratic)
     *   SBA, CROSTON, TSB                                 (untuk intermittent/lumpy)
     *   FALLBACK_LAST_KNOWN                               (FastAPI gagal)
     *
     * Idempotency: run_id (UUID) dari Laravel. Python tolak request ulang run_id yang sama.
     *
     * sigma_daily:
     *   = RMSE residual rolling-origin backtest (skala harian)
     *   Untuk pola lumpy: dikalikan faktor 1.25 (config di Python)
     *
     * baseline_mase:
     *   MASE model baseline (moving average 28 hari atau Croston klasik).
     *   Jika mase_score >= baseline_mase, model jatuh ke baseline dan
     *   model_used mencatat nama baseline.
     */
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();

            // run_id dari Laravel (UUID): kunci idempotency
            $table->uuid('run_id')->index()
                  ->comment('UUID dari Laravel. Request ulang dengan run_id sama diabaikan Python.');

            $table->foreignId('item_id')
                  ->constrained('items')
                  ->cascadeOnDelete();

            $table->timestamp('run_at')->useCurrent()
                  ->comment('Waktu Python selesai menghitung forecast');

            // ── Output utama yang dipakai Laravel Tahap 4 ─────────────────────
            $table->decimal('mu_daily', 12, 6)->nullable()
                  ->comment('Estimasi rata-rata demand harian ke depan');
            $table->decimal('sigma_daily', 12, 6)->nullable()
                  ->comment('Estimasi std deviasi error forecast harian (RMSE backtest)');

            // ── Metadata model ─────────────────────────────────────────────────
            $table->string('model_used', 50)->nullable()
                  ->comment('Nama model yang dipilih (MASE terendah)');
            $table->enum('demand_pattern_used', [
                'smooth', 'intermittent', 'erratic', 'lumpy',
            ])->nullable()->comment('Pola demand yang digunakan saat routing model');

            // ── Backtest metrics ───────────────────────────────────────────────
            $table->decimal('backtest_mae', 12, 6)->nullable();
            $table->decimal('backtest_rmse', 12, 6)->nullable();
            $table->decimal('backtest_mase', 12, 6)->nullable()
                  ->comment('MASE model terpilih. Jika >= baseline_mase → jatuh ke baseline.');
            $table->decimal('baseline_mase', 12, 6)->nullable()
                  ->comment('MASE model baseline (MA28 atau Croston). Threshold perbandingan.');

            $table->enum('status', [
                'SUCCESS',  // mu_daily dan sigma_daily valid
                'FAILED',   // FastAPI error atau output invalid (NaN/inf/negatif)
                'SKIPPED',  // SKU tidak memenuhi syarat (< 30 hari histori)
            ])->default('SUCCESS');

            $table->text('error_message')->nullable()
                  ->comment('Pesan error dari FastAPI jika status=FAILED');

            // Index untuk query "ambil forecast terbaru per item"
            $table->index(['item_id', 'run_at', 'status'], 'idx_fr_latest');
            // Catatan: run_id sudah memiliki index via ->index() pada definisi kolom UUID di atas.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
