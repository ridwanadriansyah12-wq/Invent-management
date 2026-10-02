# Deprecated Columns — Sistem ML-ROP

> **Status**: Aktif per 24 September 2026
> **Dibuat oleh**: Tim Backend — Migrasi arsitektur ML-ROP v2
> **Cleanup milestone**: Setelah sistem stabil dan backup DB divalidasi

---

## Ringkasan

Kolom-kolom di bawah ini **masih ada di database** tetapi **tidak lagi menjadi sumber kebenaran**.
Kode baru **DILARANG** membaca kolom-kolom ini untuk logika domain.
Kolom dibiarkan `NULLABLE` di DB untuk menghindari crash pada kode lama yang belum dimigrasikan.

---

## Tabel: `items`

| Kolom Lama | Status | Sumber Kebenaran Baru | Alasan Retire |
|---|---|---|---|
| `code` | ✅ **RENAMED → `sku`** | `items.sku` | Nama lebih sesuai domain supply chain |
| `safety_stock` | ⚠️ DEPRECATED | `inventory_parameters.effective_ss` | SS kini dihitung per-run oleh ParameterCalculatorService |
| `rop` | ⚠️ DEPRECATED | `inventory_parameters.effective_rop` | ROP dihitung oleh pipeline harian dengan guardrail |
| `max_stock` | ⚠️ DEPRECATED | `inventory_parameters.effective_max` | MAX dihitung oleh pipeline setelah kapasitas gudang direduksi |
| `avg_usage` | ⚠️ DEPRECATED | `forecast_runs.mu_daily` | Digantikan oleh estimasi ML yang lebih akurat |
| `planning_usage` | ⚠️ DEPRECATED | `forecast_runs.mu_daily` | Sama dengan `avg_usage`, tidak ada perbedaan semantik |
| `cv_value` | ⚠️ DEPRECATED | `item_classifications.cv2` | CV² per pola Syntetos-Boylan, dihitung Python mingguan |
| `demand_type` | ⚠️ DEPRECATED | `item_classifications.demand_pattern` | Klasifikasi 4-kuadran (smooth/erratic/intermittent/lumpy) menggantikan binary regular/intermittent |
| `coverage_period` | ⚠️ DEPRECATED | `config('inventory.target_cover_days')` per ABC class | Coverage berbeda per kelas ABC (A=14, B=21, C=30); tidak lagi satu nilai per item |
| `last_ml_update` | ⚠️ DEPRECATED | `inventory_parameters.computed_at` | Waktu komputasi sekarang di-track per-parameter-record |
| `ml_safety_stock` | ⚠️ DEPRECATED | `inventory_parameters.proposed_ss` | Nilai usulan ML sebelum guardrail |
| `ml_rop` | ⚠️ DEPRECATED | `inventory_parameters.proposed_rop` | Nilai usulan ML sebelum clamping |
| `is_manual_override` | ⚠️ DEPRECATED | `inventory_parameters.status` (APPROVED/REJECTED) | Review flow sekarang ada di tabel inventory_parameters |
| `manual_safety_stock` | ⚠️ DEPRECATED | `inventory_parameters.proposed_ss` saat reviewer APPROVED | — |
| `manual_rop` | ⚠️ DEPRECATED | `inventory_parameters.proposed_rop` saat reviewer APPROVED | — |
| `override_reason` | ⚠️ DEPRECATED | `inventory_parameters.flag_reason` + `review_notes` | Lebih terstruktur: flag sistem vs catatan reviewer dipisah |
| `override_updated_by` | ⚠️ DEPRECATED | `inventory_parameters.reviewed_by` | — |
| `override_updated_at` | ⚠️ DEPRECATED | `inventory_parameters.reviewed_at` | — |

---

## Tabel: `stock_transactions` (tabel lama)

| Status | Keterangan |
|---|---|
| ✅ **RENAMED → `stock_movements`** | Via `Schema::rename()` di migrasi `2026_09_24_100003` |
| **Model lama**: `StockTransaction` | **Model baru**: `StockMovement` |
| **Kolom `transaction_date`** | ✅ RENAMED → `movement_date` |
| **Kolom `quantity`** | ✅ RENAMED → `qty`, diperlebar ke `DECIMAL(12,3)` |
| **Kolom `reason`** | ➕ DITAMBAHKAN — kunci semantik pipeline ML |

---

## Tabel: `monthly_usages`

| Status | Keterangan |
|---|---|
| ⚠️ DEPRECATED (fungsional) | Data bulanan digantikan oleh `daily_demand` + `forecast_runs` |
| Tabel tidak di-drop | Retain untuk backward compat laporan lama |
| Pipeline Python **tidak menulis** ke tabel ini | `daily_demand` adalah sumber data ML |

---

## Panduan Migrasi Kode

### Membaca ROP/SS/MAX efektif
```php
// ❌ LAMA (deprecated)
$item->rop;
$item->safety_stock;
$item->max_stock;

// ✅ BARU
$item->load('activeParameter');
$item->activeParameter?->effective_rop;
$item->activeParameter?->effective_ss;
$item->activeParameter?->effective_max;
```

### Membaca mu_daily (rata-rata demand harian)
```php
// ❌ LAMA
$item->avg_usage;       // per bulan, tidak akurat
$item->planning_usage;  // idem

// ✅ BARU
$item->activeParameter?->forecastRun?->mu_daily;  // per hari, dari ML
```

### Membaca klasifikasi demand
```php
// ❌ LAMA
$item->demand_type;  // hanya 'regular'|'intermittent'
$item->cv_value;     // CV lama (bukan CV²)

// ✅ BARU
$item->classification?->demand_pattern;  // 'smooth'|'intermittent'|'erratic'|'lumpy'
$item->classification?->cv2;             // CV² (Syntetos-Boylan)
$item->classification?->abc_class;       // 'A'|'B'|'C'
$item->classification?->xyz_class;       // 'X'|'Y'|'Z'
```

### Mengakses gerakan stok
```php
// ❌ LAMA
$item->transactions();              // relasi ke StockTransaction
$item->transactions()->where('type', 'out')

// ✅ BARU
$item->movements();                 // relasi ke StockMovement
$item->movements()->issues()        // scope: hanya ISSUE (demand pelanggan)
$item->movements()->receipts()      // scope: hanya RECEIPT (penerimaan barang)
```

---

## Rencana Cleanup

Setelah sistem ML pipeline berjalan stabil (estimasi: setelah 3 bulan produksi):

1. Backup penuh database
2. Jalankan verifikasi: pastikan tidak ada kode aktif yang membaca kolom deprecated
3. Jalankan migrasi cleanup:
   ```
   php artisan make:migration drop_deprecated_columns_from_items
   ```
4. Kolom yang akan di-drop:
   - `safety_stock`, `rop`, `max_stock`
   - `avg_usage`, `planning_usage`, `cv_value`, `demand_type`
   - `coverage_period`, `last_ml_update`
   - `ml_safety_stock`, `ml_rop`
   - `is_manual_override`, `manual_safety_stock`, `manual_rop`
   - `override_reason`, `override_updated_by`, `override_updated_at`

---

*Dokumen ini harus diperbarui setiap kali ada kolom yang dimigrasikan atau di-drop.*
