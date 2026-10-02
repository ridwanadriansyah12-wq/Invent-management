# Dokumentasi Alur Arsitektur Sistem RoP & ML Inventory Engine

Dokumen ini menjelaskan alur operasional dan integrasi backend antara **Laravel (PHP)** dan **FastAPI (Python Microservice)** untuk manajemen persediaan barang berbasis Machine Learning, Dynamic Safety Stock (SS), Reorder Point (ROP), Maximum Stock (MAX), serta penerbitan Purchase Requisitions (PR).

---

## 1. Pembagian Tanggung Jawab (Separation of Concerns)

```
+-------------------------------------------------------------------------------+
|                                  LARAVEL (PHP)                                |
|  - Pemilik Database & Migrations                                              |
|  - Business Rules, Guardrail & Clamping (50% Tolerance)                       |
|  - Pengecekan Kapasitas Gudang (Tiered: C -> B -> A)                          |
|  - Sanitizer Kuantitas Pemesanan (MOQ & Lot Size)                             |
|  - Pemicu & Penerbitan Purchase Requisitions (PR)                             |
|  - Human-in-the-Loop Review Endpoints (Approve / Reject)                      |
|  - Master Scheduler (Daily Pipeline & Weekly Classification)                  |
+---------------------------------------+---------------------------------------+
                                        | HTTP (Batch per SKU / JSON)
                                        v
+-------------------------------------------------------------------------------+
|                            FASTAPI (PYTHON MICROSERVICE)                      |
|  - Data Pipeline (Deteksi Censoring & Imputasi Rata-rata Non-Censored 28H)     |
|  - Klasifikasi Berkala: ABC Pareto (80/15/5), XYZ (CV), ADI/CV² (Pola Demand) |
|  - Model Routing:                                                             |
|      * Smooth & Erratic       -> LightGBM + AutoETS + Moving Average 28D      |
|      * Intermittent & Lumpy   -> Syntetos-Boylan Approximation (SBA)          |
|  - Rolling-Origin Backtest (Minimal 3 Fold, Horizon = Lead Time)              |
|  - Seleksi Model berbasis MASE Terendah (Fallback ke Baseline jika ML kalah)  |
|  - Hanya mengembalikan angka forecast (mu_daily & sigma_daily)                |
+-------------------------------------------------------------------------------+
```

---

## 2. Diagram Alur Pipeline Harian (Daily Pipeline)

Dijalankan otomatis setiap hari melalui Artisan command `inventory:pipeline-daily` (jam 01:00):

```mermaid
flowchart TD
    Start([Mulai: Scheduler Harian]) --> LoadSKU[Ambil Semua SKU Aktif & Gudang]
    LoadSKU --> CallFastAPI{Panggil Python FastAPI<br>/api/v1/forecast/batch}
    
    CallFastAPI -- Sukses --> ParseML[Ambil mu_daily & sigma_daily]
    CallFastAPI -- Timeout / Gagal / Offline --> FallbackML[Tandai ML Unavailable]
    
    ParseML --> GuardrailCheck
    FallbackML --> GuardrailCheck
    
    subgraph Guardrail ["Tahap 5: Guardrail & Filter Zero-Trust"]
        GuardrailCheck{Cek Kondisi SKU}
        GuardrailCheck -- "Umur < 30 Hari" --> StaticRoute[Rute STATIC_CATEGORY<br>Status: ACTIVE]
        GuardrailCheck -- "ML Gagal / Invalid" --> FallbackRoute[Rute FALLBACK_LAST_APPROVED<br>Jika belum ada: STATIC_CATEGORY]
        GuardrailCheck -- "Umur >= 30 Hari & ML Valid" --> CalcParam[Hitung Proposed SS, ROP, MAX<br>via ParameterCalculator]
        
        CalcParam --> BaselineCheck[Hitung Baseline ROP 30 Hari Terakhir]
        BaselineCheck --> ClampCheck{Proposed ROP di dalam<br>[0.5x, 1.5x] Baseline?}
        
        ClampCheck -- Ya --> StatusActive[Status: ACTIVE<br>Effective = Proposed]
        ClampCheck -- Tidak --> StatusPending[Status: PENDING_REVIEW<br>Effective = Clamped Bound<br>Flag: ROP_DEVIATION_GT_50PCT]
        
        StatusActive --> SupersedeOld[Supersede Parameter Lama]
        StaticRoute --> SupersedeOld
        FallbackRoute --> SupersedeOld
    end
    
    SupersedeOld --> CapacityPhase
    StatusPending --> CapacityPhase
    
    subgraph Capacity ["Tahap 6: Kapasitas Gudang"]
        CapacityPhase[Hitung Total Volume = SUM Effective MAX x Volume m³]
        CapacityPhase --> CapCheck{Total Volume <= Limit<br>Capacity x 85%?}
        CapCheck -- Ya --> CapacityOK[Kapasitas Aman]
        CapCheck -- Melebihi --> ReduceTiers[Pangkas Bertingkat:<br>1. Kelas C s/d ROP+MOQ<br>2. Kelas B s/d ROP+MOQ<br>3. Kelas A s/d ROP+MOQ]
        ReduceTiers --> CheckStillOver{Masih Melebihi Limit?}
        CheckStillOver -- Tidak --> CapacityOK
        CheckStillOver -- Ya --> AlertCap[Flag: WAREHOUSE_OVER_CAPACITY<br>Jangan paksa di bawah ROP+MOQ]
    end
    
    CapacityOK --> PRPhase
    AlertCap --> PRPhase
    
    subgraph PRTrigger ["Tahap 7: Sanitizer & Purchase Requisition"]
        PRPhase[Hitung Inventory Position = StockOnHand + Outstanding PRs]
        PRPhase --> CheckReorder{Inventory Position <= Effective ROP<br>DAN Belum Ada PR OPEN?}
        CheckReorder -- Tidak --> NoPR[Lewati SKU]
        CheckReorder -- Ya --> CalcRaw[q_raw = Effective MAX - Inventory Position]
        CalcRaw --> Sanitizer[OrderQuantitySanitizer:<br>Final Q = max MOQ, ceil q_raw / LotSize x LotSize]
        Sanitizer --> VolumeProj{Proyeksi Volume Baru <= Kapasitas Gudang?}
        VolumeProj -- Ya --> CreatePROK[Buat PR Status: OPEN, Volume Flag: False]
        VolumeProj -- Tidak --> CreatePRFlag[Buat PR Status: OPEN, Volume Flag: True<br>Catatan: VOLUME_EXCEEDS_WAREHOUSE_CAPACITY]
    end
    
    NoPR --> Finish([Selesai])
    CreatePROK --> Finish
    CreatePRFlag --> Finish
```

---

## 3. Rumus & Logika Matematis

### 3.1 ParameterCalculator (Tahap 4)
* **Lead Time**: $LT = \text{lead\_time\_days}$, $\sigma_{LT} = \text{lead\_time\_std\_days}$
* **Service Level Z-Score**:
  * Kelas A ($98\%$): $Z \approx 2.0537$
  * Kelas B ($95\%$): $Z \approx 1.6449$
  * Kelas C ($90\%$): $Z \approx 1.2816$
* **Safety Stock (SS)**:
  $$\text{SS} = \left\lceil Z \times \sqrt{LT \cdot \sigma_{\text{daily}}^2 + \mu_{\text{daily}}^2 \cdot \sigma_{LT}^2} \right\rceil$$
* **Reorder Point (ROP)**:
  $$\text{ROP} = \left\lceil \mu_{\text{daily}} \times LT + \text{SS} \right\rceil$$
* **Target Order (Q_target)**:
  $$Q_{\text{target}} = \mu_{\text{daily}} \times \text{target\_cover\_days}$$
  *(Cover Days: A = 14 hari, B = 21 hari, C = 30 hari)*
* **Maximum Stock (MAX)**:
  $$\text{MAX} = \left\lceil \text{ROP} + Q_{\text{target}} \right\rceil$$

### 3.2 Guardrail & Clamping (Tahap 5)
* **Batas Clamping**:
  $$\text{Lower} = 0.50 \times \text{Baseline ROP}, \quad \text{Upper} = 1.50 \times \text{Baseline ROP}$$
* Jika $\text{Proposed ROP} \notin [\text{Lower}, \text{Upper}]$:
  * $\text{Effective ROP} = \text{clamp}(\text{Proposed ROP}, \text{Lower}, \text{Upper})$
  * $\text{Effective SS} = \max(0, \lceil \text{Effective ROP} - \mu_{\text{daily}} \times LT \rceil)$
  * $\text{Effective MAX} = \lceil \text{Effective ROP} + Q_{\text{target}} \rceil$
  * $\text{Status} = \text{PENDING\_REVIEW}$, $\text{flag\_reason} = \text{'ROP\_DEVIATION\_GT\_50PCT'}$

### 3.3 Sanitizer Pemesanan (Tahap 7)
$$\text{Final Q} = \max\left(\text{MOQ}, \left\lceil \frac{q_{\text{raw}}}{\text{LotSize}} \right\rceil \times \text{LotSize}\right)$$

---

## 4. Endpoint Review (Human-in-the-Loop)

1. **Daftar Parameter Menunggu Peninjauan**:
   * `GET /api/v1/inventory/parameters/pending`
   * Mengembalikan daftar SKU yang terkena clamping dengan status `PENDING_REVIEW`.

2. **Persetujuan (Approve)**:
   * `POST /api/v1/inventory/parameters/{id}/approve`
   * Mengubah status menjadi `APPROVED`, memulihkan nilai usulan asli (`effective = proposed`), mengisi `reviewed_by` dan `reviewed_at`, serta me-supersede parameter lama.

3. **Penolakan (Reject)**:
   * `POST /api/v1/inventory/parameters/{id}/reject`
   * Mengubah status menjadi `REJECTED`, mempertahankan nilai aman hasil clamping, mengisi `reviewed_by` dan `reviewed_at`.
