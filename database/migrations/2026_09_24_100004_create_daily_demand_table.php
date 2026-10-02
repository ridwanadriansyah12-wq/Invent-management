<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * daily_demand — Ledger permintaan harian per SKU.
     *
     * Diisi oleh Python FastAPI via SQLAlchemy (INSERT ON DUPLICATE KEY UPDATE).
     * Sumber data: rekonstruksi dari stock_movements.
     *
     * Logika opening_stock:
     *   opening_stock[t] = closing_stock[t-1] + receipt_qty[t]
     *   closing_stock[t] = opening_stock[t] - issued_qty[t]
     *
     * Censoring:
     *   is_censored = TRUE jika issued_qty ≈ 0 DAN opening_stock ≈ 0
     *   (toleransi < 0.0005 untuk menghindari galat floating point DECIMAL)
     *
     * Imputasi:
     *   demand_clean        = issued_qty          (hari non-censored)
     *   demand_clean        = rata-rata non-censored 28 hari terakhir (hari censored)
     *   imputation_method   = 'NONE' | 'MEAN_UNCENSORED_28D' | 'INSUFFICIENT_DATA'
     *
     * Python menulis tabel ini; Laravel hanya membaca.
     * Kunci unik (item_id, date) menjamin idempotency upsert.
     */
    public function up(): void
    {
        Schema::create('daily_demand', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')
                  ->constrained('items')
                  ->cascadeOnDelete();

            $table->date('date')->comment('Tanggal kalender (satu baris per item per hari)');

            // Stok awal hari (setelah penerimaan hari itu diperhitungkan)
            $table->decimal('opening_stock', 12, 3)->default(0);

            // Jumlah penerimaan barang hari itu (reason=RECEIPT)
            $table->decimal('receipt_qty', 12, 3)->default(0);

            // Jumlah yang dikeluarkan hari itu (reason=ISSUE, tidak diubah dari ledger asli)
            $table->decimal('issued_qty', 12, 3)->default(0)
                  ->comment('Nilai asli dari stock_movements. JANGAN dimodifikasi.');

            // TRUE jika issued_qty ≈ 0 DAN opening_stock ≈ 0 (stock-out terdeteksi)
            $table->boolean('is_censored')->default(false);

            // Nilai bersih permintaan yang dipakai training (setelah imputasi/validasi)
            // NULL = data tidak cukup untuk imputasi (INSUFFICIENT_DATA)
            $table->decimal('demand_clean', 12, 3)->nullable()
                  ->comment('demand_clean dipakai ML. NULL = dikeluarkan dari training.');

            $table->enum('imputation_method', [
                'NONE',                 // Hari non-censored, demand_clean = issued_qty
                'MEAN_UNCENSORED_28D',  // Hari censored, imputasi rata-rata 28 hari
                'MEAN_UNCENSORED_FULL', // Hari censored, window diperluas ke seluruh histori
                'INSUFFICIENT_DATA',    // Data tidak cukup, demand_clean = NULL
            ])->default('NONE');

            $table->timestamps();

            // Kunci unik: idempotency untuk upsert Python
            $table->unique(['item_id', 'date'], 'uq_daily_demand_item_date');

            // Index untuk query pipeline (range scan per item)
            $table->index(['item_id', 'date', 'is_censored'], 'idx_dd_pipeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_demand');
    }
};
