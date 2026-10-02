<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inventory & ML Forecasting Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Parameter global sistem persediaan gudang berbasis Machine Learning
    | dan Dynamic Safety Stock sesuai spesifikasi SRS & Arsitektur RoP.
    |
    */

    // Lead time default bila tidak ditentukan pada level item
    'lead_time_days_default'     => (int) env('INVENTORY_DEFAULT_LEAD_TIME_DAYS', 15),
    'lead_time_std_days_default' => (float) env('INVENTORY_DEFAULT_LEAD_TIME_STD_DAYS', 0.0),

    // Service Level per kelas ABC (probabilitas tidak terjadi stockout)
    // Z-Score dihitung dengan inverse normal CDF:
    // A (98%): Z ≈ 2.0537
    // B (95%): Z ≈ 1.6449
    // C (90%): Z ≈ 1.2816
    'service_level' => [
        'A' => [
            'level'   => 0.98,
            'z_score' => 2.0537489106318225,
        ],
        'B' => [
            'level'   => 0.95,
            'z_score' => 1.6448536269514722,
        ],
        'C' => [
            'level'   => 0.90,
            'z_score' => 1.2815515655446004,
        ],
        'default' => [
            'level'   => 0.90,
            'z_score' => 1.2815515655446004,
        ],
    ],

    // Target cover days untuk penentuan kuantitas pesanan Q_target = mu_daily * target_cover_days
    'target_cover_days' => [
        'A'       => (int) env('INVENTORY_COVER_DAYS_A', 14),
        'B'       => (int) env('INVENTORY_COVER_DAYS_B', 21),
        'C'       => (int) env('INVENTORY_COVER_DAYS_C', 30),
        'default' => (int) env('INVENTORY_COVER_DAYS_DEFAULT', 30),
    ],

    // Kapasitas utilisasi maksimum gudang sebelum capacity reduction dipicu
    'max_warehouse_utilization' => (float) env('INVENTORY_MAX_WAREHOUSE_UTILIZATION', 0.85),

    // Syarat umur SKU minimum untuk diikutkan ke pipeline ML (hari sejak first_movement_date)
    'min_history_days_ml' => (int) env('INVENTORY_MIN_HISTORY_DAYS_ML', 30),

    // Batas toleransi clamping ROP (50% dari baseline)
    // lower = 0.50 * baseline, upper = 1.50 * baseline
    'clamp_pct' => (float) env('INVENTORY_CLAMP_PCT', 0.50),

    // Imputasi & Censoring parameter
    'imputation_window_days' => (int) env('INVENTORY_IMPUTATION_WINDOW_DAYS', 28),
    'min_uncensored_days'    => (int) env('INVENTORY_MIN_UNCENSORED_DAYS', 14),

    // Microservice FastAPI Python connection
    'fastapi' => [
        'url'      => env('FASTAPI_URL', 'http://127.0.0.1:8000'),
        'api_key'  => env('FASTAPI_API_KEY', 'test-api-key'),
        'timeout'  => (int) env('FASTAPI_TIMEOUT', 30), // detik
        'retry'    => (int) env('FASTAPI_RETRY_COUNT', 2),
    ],
];
