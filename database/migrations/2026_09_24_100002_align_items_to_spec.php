<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyesuaikan tabel items dengan spesifikasi sistem ML-ROP.
     *
     * Tindakan:
     *   ADD    : warehouse_id, sku alias (via rename code→sku), unit_cost, volume_m3,
     *            moq, lot_size, lead_time_std_days, first_movement_date
     *   ALTER  : lead_time_days default 7→15
     *   NULLABLE: semua kolom deprecated agar INSERT baru tidak error
     *
     * Kolom deprecated TIDAK di-drop di sini (lihat docs/deprecated-columns.md).
     * Kolom code direname menjadi sku; isi data lama otomatis terbawa.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // ── 1. Tambah FK warehouse ─────────────────────────────────────────
            // Nullable karena data lama belum punya warehouse
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->after('category_id')
                  ->constrained('warehouses')
                  ->nullOnDelete()
                  ->comment('Gudang tempat SKU ini disimpan');

            // ── 2. Rename code → sku ───────────────────────────────────────────
            // Schema::table()->renameColumn() aman untuk data lama
            $table->renameColumn('code', 'sku');

            // ── 3. Kolom baru domain ML ────────────────────────────────────────
            $table->decimal('unit_cost', 15, 4)->default(0)->after('unit')
                  ->comment('Harga per unit, dipakai hitung annual_value ABC');

            $table->decimal('volume_m3', 10, 6)->default(0)->after('unit_cost')
                  ->comment('Volume per unit dalam m³, untuk cek kapasitas gudang. Wajib > 0 untuk SKU aktif.');

            $table->decimal('moq', 12, 3)->default(1)->after('volume_m3')
                  ->comment('Minimum Order Quantity. Harus > 0 dan kelipatan lot_size (toleransi 0.0001)');

            $table->decimal('lot_size', 12, 3)->default(1)->after('moq')
                  ->comment('Ukuran lot/paket order. Harus > 0');

            // lead_time_std_days: simpangan baku lead time antar siklus pengiriman
            $table->decimal('lead_time_std_days', 5, 2)->default(0)->after('lead_time_days')
                  ->comment('Std deviasi lead time (hari). 0 = lead time deterministik');

            // Tanggal gerakan pertama: dipakai guardrail untuk cek umur SKU
            $table->date('first_movement_date')->nullable()->after('lead_time_std_days')
                  ->comment('Tanggal movement pertama SKU. NULL = belum ada history. Diset otomatis pipeline.');
        });

        // ── 4. Ubah default lead_time_days 7 → 15 ─────────────────────────────
        // Gunakan raw statement agar cross-DB (MySQL & PostgreSQL)
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE items ALTER COLUMN lead_time_days SET DEFAULT 15'
        );

        // ── 5. Buat kolom deprecated NULLABLE ─────────────────────────────────
        // Sehingga INSERT baru yang tidak mengisi kolom lama tidak error.
        // Data lama tidak disentuh.
        Schema::table('items', function (Blueprint $table) {
            // Kolom dari migrasi 2024_01_01_000003
            $table->decimal('safety_stock', 10, 2)->nullable()->default(null)->change();
            $table->decimal('rop', 10, 2)->nullable()->default(null)->change();
            $table->decimal('max_stock', 10, 2)->nullable()->default(null)->change();
            $table->decimal('avg_usage', 10, 4)->nullable()->default(null)->change();
            $table->decimal('planning_usage', 10, 4)->nullable()->default(null)->change();
            $table->decimal('cv_value', 8, 4)->nullable()->default(null)->change();
            $table->unsignedSmallInteger('coverage_period')->nullable()->default(null)->change();

            // Kolom dari migrasi 2026_09_21_000001
            $table->decimal('ml_safety_stock', 10, 2)->nullable()->default(null)->change();
            $table->decimal('ml_rop', 10, 2)->nullable()->default(null)->change();
            $table->boolean('is_manual_override')->default(false)->change();
            $table->decimal('manual_safety_stock', 10, 2)->nullable()->default(null)->change();
            $table->decimal('manual_rop', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Hapus FK dan kolom baru
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'warehouse_id',
                'unit_cost',
                'volume_m3',
                'moq',
                'lot_size',
                'lead_time_std_days',
                'first_movement_date',
            ]);

            // Kembalikan nama sku → code
            $table->renameColumn('sku', 'code');
        });

        // Kembalikan default lead_time_days ke 7
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE items ALTER COLUMN lead_time_days SET DEFAULT 7'
        );
    }
};
