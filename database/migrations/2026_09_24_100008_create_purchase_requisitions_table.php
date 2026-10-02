<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * purchase_requisitions — PR yang diterbitkan otomatis oleh sistem (Tahap 7).
     *
     * Diterbitkan HANYA oleh Laravel. Python dan operator tidak membuat PR langsung.
     *
     * Trigger harian (Laravel Scheduler):
     *   IF inventory_position <= effective_rop AND tidak ada PR OPEN untuk SKU ini
     *     q_raw = effective_max - inventory_position
     *     q_final = OrderQuantitySanitizer::compute(q_raw, moq, lot_size)
     *     Buat record PR baru
     *
     * inventory_position = stock_on_hand + Σ(q_final PR berstatus OPEN/ORDERED yang belum diterima)
     *
     * Sanitizer (kelas terpisah OrderQuantitySanitizer):
     *   q_final = max(moq, ceil(q_raw / lot_size) × lot_size)
     *   Semua operasi menggunakan bcmath agar tidak ada galat floating point.
     *
     * Status lifecycle:
     *   OPEN     → baru diterbitkan, menunggu tindakan procurement
     *   ORDERED  → sudah dikonversi menjadi PO ke supplier
     *   RECEIVED → barang sudah diterima di gudang (PO selesai)
     *   CANCELLED→ dibatalkan (manual atau otomatis karena stok terpenuhi)
     *
     * Flag volume:
     *   volume_flag = TRUE jika q_final × volume_m3 akan membuat total proyeksi
     *   melebihi kapasitas gudang. PR tetap dibuat tetapi ditandai untuk review.
     */
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                  ->constrained('items')
                  ->restrictOnDelete()
                  ->comment('SKU yang membutuhkan pengadaan');

            // FK ke inventory_parameters: parameter mana yang mentrigger PR ini
            $table->foreignId('parameter_id')
                  ->nullable()
                  ->constrained('inventory_parameters')
                  ->nullOnDelete()
                  ->comment('Baris inventory_parameters ACTIVE/APPROVED yang dipakai saat PR dibuat');

            // ── Snapshot saat PR dibuat ────────────────────────────────────────
            $table->decimal('inventory_position', 12, 3)
                  ->comment('stock_on_hand + Σ q_final PR OPEN/ORDERED, dihitung saat trigger');
            $table->decimal('effective_rop_snapshot', 12, 3)
                  ->comment('Snapshot effective_rop saat PR dibuat (untuk audit)');
            $table->decimal('effective_max_snapshot', 12, 3)
                  ->comment('Snapshot effective_max saat PR dibuat (untuk audit)');

            // ── Kalkulasi Quantity ─────────────────────────────────────────────
            $table->decimal('q_raw', 12, 3)
                  ->comment('effective_max - inventory_position (sebelum sanitizer)');
            $table->decimal('q_final', 12, 3)
                  ->comment('max(moq, ceil(q_raw/lot_size)×lot_size). Nilai yang dipesan.');
            $table->decimal('moq_snapshot', 12, 3)
                  ->comment('Snapshot MOQ item saat PR dibuat');
            $table->decimal('lot_size_snapshot', 12, 3)
                  ->comment('Snapshot lot_size item saat PR dibuat');

            // ── Status & Flag ──────────────────────────────────────────────────
            $table->enum('status', [
                'OPEN',
                'ORDERED',
                'RECEIVED',
                'CANCELLED',
            ])->default('OPEN');

            // Flag jika q_final × volume_m3 proyeksi melebihi kapasitas gudang
            $table->boolean('volume_flag')->default(false)
                  ->comment('TRUE = perlu review manual karena proyeksi melebihi kapasitas gudang');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu PR OPEN per item (cegah duplikasi trigger)
            // Ini adalah partial unique index: hanya OPEN per item
            // Diterapkan via logika query di Laravel (tidak bisa partial index di semua DB)
            $table->index(['item_id', 'status', 'created_at'], 'idx_pr_item_status');
            $table->index(['status', 'created_at'], 'idx_pr_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisitions');
    }
};
