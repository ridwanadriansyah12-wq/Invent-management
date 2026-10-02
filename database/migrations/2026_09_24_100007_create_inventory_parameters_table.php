<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * inventory_parameters — Sumber kebenaran tunggal ROP/SS/MAX efektif per SKU.
     *
     * Diisi dan dikelola HANYA oleh Laravel (Tahap 4 & 5). Python TIDAK menulis tabel ini.
     *
     * Status lifecycle:
     *   ACTIVE         → parameter berlaku, diproduksi oleh ML atau statis, dalam batas clamping
     *   PENDING_REVIEW → ML mengusulkan ROP yang melompat > 50% dari baseline; menunggu approval
     *   APPROVED       → tim procurement menyetujui parameter PENDING_REVIEW; effective = proposed
     *   REJECTED       → tim procurement menolak; effective tetap nilai clamp (batas 50%)
     *   SUPERSEDED     → parameter lama, digantikan oleh record baru ACTIVE/APPROVED
     *
     * Kolom proposed_* : nilai mentah dari kalkulasi ML (sebelum clamping)
     * Kolom effective_*: nilai yang benar-benar dipakai untuk PR trigger (setelah clamping)
     *
     * Guardrail clamping (Tahap 5):
     *   baseline = rata-rata effective_rop 30 hari terakhir (atau statis kategori jika baru)
     *   lower = 0.5 × baseline, upper = 1.5 × baseline
     *   Jika proposed_rop ∉ [lower, upper] → PENDING_REVIEW, effective = clamp(proposed, lower, upper)
     *   Jika proposed_rop ∈ [lower, upper] → ACTIVE, effective = proposed
     *
     * Semua nilai DECIMAL(12,3) — konsisten dengan keputusan qty desimal.
     */
    public function up(): void
    {
        Schema::create('inventory_parameters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                  ->constrained('items')
                  ->cascadeOnDelete();

            $table->timestamp('computed_at')->useCurrent()
                  ->comment('Waktu Laravel menghitung parameter ini');

            // ── Sumber parameter ───────────────────────────────────────────────
            $table->enum('source', [
                'ML',                   // Dari output FastAPI (mu_daily, sigma_daily valid)
                'STATIC_CATEGORY',      // SKU baru < 30 hari, pakai category_defaults
                'FALLBACK_LAST_APPROVED', // FastAPI gagal, pakai parameter ACTIVE/APPROVED terakhir
            ])->comment('Asal kalkulasi. Menentukan apakah clamping berlaku.');

            // FK ke forecast_runs (nullable: STATIC_CATEGORY dan FALLBACK tidak punya run)
            $table->foreignId('forecast_run_id')
                  ->nullable()
                  ->constrained('forecast_runs')
                  ->nullOnDelete();

            // ── Nilai Usulan (sebelum clamping) ───────────────────────────────
            $table->decimal('proposed_ss', 12, 3)->nullable()
                  ->comment('SS = Z × sqrt(LT×σ² + μ²×σ_LT²)');
            $table->decimal('proposed_rop', 12, 3)->nullable()
                  ->comment('ROP = μ×LT + SS');
            $table->decimal('proposed_max', 12, 3)->nullable()
                  ->comment('MAX = ROP + μ×target_cover_days');

            // ── Nilai Efektif (setelah clamping, yang dipakai PR trigger) ─────
            $table->decimal('effective_ss', 12, 3)->nullable();
            $table->decimal('effective_rop', 12, 3)->nullable();
            $table->decimal('effective_max', 12, 3)->nullable();

            // Snapshot effective_max sebelum reduksi kapasitas gudang (untuk audit)
            $table->decimal('effective_max_before_capacity', 12, 3)->nullable()
                  ->comment('Nilai effective_max sebelum reduksi kapasitas Tahap 6');

            // ── Status Lifecycle ───────────────────────────────────────────────
            $table->enum('status', [
                'ACTIVE',
                'PENDING_REVIEW',
                'APPROVED',
                'REJECTED',
                'SUPERSEDED',
            ])->default('ACTIVE');

            // ── Guardrail & Review Info ────────────────────────────────────────
            $table->string('flag_reason', 255)->nullable()
                  ->comment('Alasan flag: ROP_DEVIATION_GT_50PCT | ML_INVALID_OR_UNAVAILABLE | WAREHOUSE_OVER_CAPACITY');

            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User procurement yang approve/reject (hanya untuk PENDING_REVIEW)');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable()
                  ->comment('Catatan reviewer saat approve/reject');

            // ── Snapshot parameter kalkulasi (untuk audit/reproduct) ───────────
            $table->decimal('snapshot_mu_daily', 12, 6)->nullable()
                  ->comment('mu_daily dari forecast_runs saat kalkulasi');
            $table->decimal('snapshot_sigma_daily', 12, 6)->nullable();
            $table->decimal('snapshot_z_score', 8, 6)->nullable()
                  ->comment('Z-score service level yang dipakai (dari config ABC class)');
            $table->unsignedSmallInteger('snapshot_lead_time_days')->nullable();
            $table->decimal('snapshot_lead_time_std', 5, 2)->nullable();

            $table->timestamps();

            // Hanya satu ACTIVE/APPROVED per item pada satu waktu
            // (dikelola logika Laravel, bukan DB constraint, karena state machine)
            $table->index(['item_id', 'status', 'computed_at'], 'idx_ip_active');
            $table->index(['status', 'computed_at'], 'idx_ip_pending_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_parameters');
    }
};
