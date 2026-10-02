<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename stock_transactions → stock_movements dan sesuaikan struktur:
     *
     * RENAME TABLE : stock_transactions → stock_movements
     *
     * KOLOM BARU:
     *   movement_date : alias pengganti transaction_date (rename)
     *   reason        : menggantikan ambiguitas tipe 'adjustment'.
     *                   Enum: ISSUE|RECEIPT|RETURN|ADJUSTMENT|OPENING_BALANCE
     *                   Hanya ISSUE yang dihitung sebagai demand dalam pipeline ML.
     *
     * KOLOM DIPERTAHANKAN (tidak di-drop):
     *   type           : uppercase enum baru IN|OUT (nilai lama in|out dimigrasi data via seeder/job)
     *   quantity       : diperlebar ke DECIMAL(12,3) dari DECIMAL(10,2) untuk ketelitian
     *   stock_before   : audit trail, retain
     *   stock_after    : audit trail, retain
     *   reference_no   : operasional, retain
     *   notes          : operasional, retain
     *   user_id        : audit trail, retain
     *   transaction_date: DEPRECATED → diganti movement_date (retain untuk backward compat)
     *
     * INDEX:
     *   Tambah index (item_id, movement_date, reason) untuk query pipeline harian
     */
    public function up(): void
    {
        // ── 1. Rename tabel ────────────────────────────────────────────────────
        Schema::rename('stock_transactions', 'stock_movements');

        // ── 2. Modifikasi kolom dan tambah kolom baru ──────────────────────────
        Schema::table('stock_movements', function (Blueprint $table) {
            // Rename transaction_date → movement_date
            $table->renameColumn('transaction_date', 'movement_date');

            // Rename quantity → qty dan perlebar presisi ke DECIMAL(12,3)
            $table->renameColumn('quantity', 'qty');

            // Tambah kolom reason: kunci semantik untuk pipeline ML
            // ISSUE   = keluar karena pemakaian/distribusi → demand
            // RECEIPT = penerimaan barang masuk
            // RETURN  = barang dikembalikan ke gudang
            // ADJUSTMENT = koreksi opname/rusak (tidak dihitung demand)
            // OPENING_BALANCE = saldo awal SKU
            $table->enum('reason', [
                'ISSUE',
                'RECEIPT',
                'RETURN',
                'ADJUSTMENT',
                'OPENING_BALANCE',
            ])->after('type')
              ->default('ISSUE')
              ->comment('Semantik gerakan stok. Hanya ISSUE = demand pelanggan.');

            // Index tambahan untuk pipeline daily_demand
            $table->index(['item_id', 'movement_date', 'reason'], 'idx_sm_pipeline');
        });

        // ── 3. Perlebar presisi qty DECIMAL(10,2) → DECIMAL(12,3) ──────────────
        // Dilakukan via raw statement karena renameColumn + change() bersamaan
        // bisa konflik di beberapa versi Doctrine DBAL
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE stock_movements MODIFY COLUMN qty DECIMAL(12,3) NOT NULL'
        );

        // ── 4. Backfill reason berdasarkan type lama ───────────────────────────
        // type='in'         → reason='RECEIPT'
        // type='out'        → reason='ISSUE'
        // type='adjustment' → reason='ADJUSTMENT'
        \Illuminate\Support\Facades\DB::statement("
            UPDATE stock_movements
            SET reason = CASE
                WHEN type = 'in'         THEN 'RECEIPT'
                WHEN type = 'out'        THEN 'ISSUE'
                WHEN type = 'adjustment' THEN 'ADJUSTMENT'
                ELSE 'ISSUE'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_sm_pipeline');
            $table->dropColumn('reason');
            $table->renameColumn('movement_date', 'transaction_date');
            $table->renameColumn('qty', 'quantity');
        });

        // Kembalikan presisi
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE stock_movements MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL'
        );

        Schema::rename('stock_movements', 'stock_transactions');
    }
};
