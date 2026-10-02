<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use App\Models\SettingAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SettingsService — Pintu gerbang tunggal pembacaan dan pembaruan konfigurasi inventaris.
 *
 * Alur Pembacaan:
 *   1. Cache (10 menit / 600 detik)
 *   2. Database (tabel `app_settings`)
 *   3. Konfigurasi default (config/inventory.php)
 *
 * Kunci Rahasia (.env seperti FastAPI API key & DB credentials) TIDAK disimpan di database
 * dan tidak diekspos melalui service ini.
 */
class SettingsService
{
    public const CACHE_PREFIX = 'prism_setting:';
    public const CACHE_TTL_SECONDS = 600; // 10 menit

    /**
     * Ambil nilai pengaturan berdasarkan kunci.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $normalizedKey = preg_replace('/^inventory\./', '', $key);
        $cacheKey = self::CACHE_PREFIX . $normalizedKey;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalizedKey, $default) {
            try {
                $record = AppSetting::where('key', $normalizedKey)->first();
                if ($record !== null && array_key_exists('value', $record->value ?? [])) {
                    return $record->value['value'];
                }
            } catch (\Throwable) {
                // Fallback ke config jika tabel app_settings belum ada / koneksi db testing
            }

            return config("inventory.{$normalizedKey}", $default);
        });
    }

    /**
     * Ambil semua parameter operasional inventaris sebagai array terstruktur.
     */
    public function getAll(): array
    {
        return [
            'lead_time_days_default'     => (int) $this->get('lead_time_days_default', 15),
            'lead_time_std_days_default' => (float) $this->get('lead_time_std_days_default', 0.0),
            'service_level'              => $this->getServiceLevels(),
            'target_cover_days'          => $this->getTargetCoverDays(),
            'max_warehouse_utilization'  => (float) $this->get('max_warehouse_utilization', 0.85),
            'clamp_pct'                  => (float) $this->get('clamp_pct', 0.50),
            'lumpy_sigma_factor'         => (float) $this->get('lumpy_sigma_factor', 1.25),
            'min_history_days_ml'        => (int) $this->get('min_history_days_ml', 30),
            'imputation_window_days'     => (int) $this->get('imputation_window_days', 28),
            'min_uncensored_days'        => (int) $this->get('min_uncensored_days', 14),
        ];
    }

    /**
     * Dapatkan service level dan Z-score terhitung per kelas ABC.
     */
    public function getServiceLevels(): array
    {
        $levels = $this->get('service_level');

        if (!is_array($levels)) {
            $levels = config('inventory.service_level', []);
        }

        $result = [];
        foreach (['A', 'B', 'C', 'default'] as $tier) {
            $p = (float) ($levels[$tier]['level'] ?? 0.90);
            $z = (float) ($levels[$tier]['z_score'] ?? $this->calculateZScore($p));
            $result[$tier] = [
                'level'   => $p,
                'z_score' => $z,
            ];
        }

        return $result;
    }

    /**
     * Dapatkan target cover days per kelas ABC.
     */
    public function getTargetCoverDays(): array
    {
        $days = $this->get('target_cover_days');

        if (!is_array($days)) {
            $days = config('inventory.target_cover_days', []);
        }

        return [
            'A'       => (int) ($days['A'] ?? 14),
            'B'       => (int) ($days['B'] ?? 21),
            'C'       => (int) ($days['C'] ?? 30),
            'default' => (int) ($days['default'] ?? 30),
        ];
    }

    /**
     * Dapatkan Z-score untuk kelas tertentu ('A', 'B', 'C').
     */
    public function getZScore(string $abcClass): float
    {
        $classKey = strtoupper(trim($abcClass));
        $levels = $this->getServiceLevels();

        return (float) ($levels[$classKey]['z_score'] ?? $levels['default']['z_score'] ?? 1.28155);
    }

    /**
     * Dapatkan target cover days untuk kelas tertentu.
     */
    public function getCoverDaysForClass(string $abcClass): int
    {
        $classKey = strtoupper(trim($abcClass));
        $coverDays = $this->getTargetCoverDays();

        return (int) ($coverDays[$classKey] ?? $coverDays['default'] ?? 30);
    }

    /**
     * Simpan pembaruan pengaturan dengan validasi rentang dan pencatatan audit.
     *
     * @param array<string, mixed> $settings Pasangan key-value yang diperbarui
     * @param int|null $userId ID Admin yang melakukan perubahan
     * @throws InvalidArgumentException Jika nilai di luar rentang valid
     */
    public function updateMany(array $settings, ?int $userId = null): void
    {
        $validated = $this->validateRanges($settings);

        DB::transaction(function () use ($validated, $userId) {
            foreach ($validated as $key => $newValue) {
                $oldValue = $this->get($key);

                AppSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value'      => ['value' => $newValue],
                        'updated_by' => $userId,
                    ]
                );

                SettingAudit::create([
                    'user_id'     => $userId,
                    'setting_key' => $key,
                    'old_value'   => ['value' => $oldValue],
                    'new_value'   => ['value' => $newValue],
                    'created_at'  => now(),
                ]);

                // Hapus cache agar pembaruan langsung terbaca
                Cache::forget(self::CACHE_PREFIX . $key);
            }
        });
    }

    /**
     * Ambil snapshot lengkap untuk disimpan di pipeline_runs.settings_snapshot.
     */
    public function getSnapshot(): array
    {
        return $this->getAll();
    }

    /**
     * Validasi batas rentang operasional yang wajar.
     */
    public function validateRanges(array $input): array
    {
        $validated = [];

        if (array_key_exists('lead_time_days_default', $input)) {
            $val = (int) $input['lead_time_days_default'];
            if ($val < 1 || $val > 365) {
                throw new InvalidArgumentException("lead_time_days_default harus antara 1 dan 365 hari, bernilai: {$val}");
            }
            $validated['lead_time_days_default'] = $val;
        }

        if (array_key_exists('lead_time_std_days_default', $input)) {
            $val = (float) $input['lead_time_std_days_default'];
            if ($val < 0.0 || $val > 90.0) {
                throw new InvalidArgumentException("lead_time_std_days_default harus antara 0.0 dan 90.0 hari, bernilai: {$val}");
            }
            $validated['lead_time_std_days_default'] = $val;
        }

        if (array_key_exists('max_warehouse_utilization', $input)) {
            $val = (float) $input['max_warehouse_utilization'];
            if ($val <= 0.0 || $val > 1.0) {
                throw new InvalidArgumentException("max_warehouse_utilization harus antara 0 (eksklusif) dan 1.0 (inklusif), bernilai: {$val}");
            }
            $validated['max_warehouse_utilization'] = $val;
        }

        if (array_key_exists('clamp_pct', $input)) {
            $val = (float) $input['clamp_pct'];
            if ($val <= 0.05 || $val > 1.0) {
                throw new InvalidArgumentException("clamp_pct harus antara 0.05 dan 1.0, bernilai: {$val}");
            }
            $validated['clamp_pct'] = $val;
        }

        if (array_key_exists('min_history_days_ml', $input)) {
            $val = (int) $input['min_history_days_ml'];
            if ($val < 14 || $val > 365) {
                throw new InvalidArgumentException("min_history_days_ml harus antara 14 dan 365 hari, bernilai: {$val}");
            }
            $validated['min_history_days_ml'] = $val;
        }

        if (array_key_exists('lumpy_sigma_factor', $input)) {
            $val = (float) $input['lumpy_sigma_factor'];
            if ($val < 1.0 || $val > 3.0) {
                throw new InvalidArgumentException("lumpy_sigma_factor harus antara 1.0 dan 3.0, bernilai: {$val}");
            }
            $validated['lumpy_sigma_factor'] = $val;
        }

        if (array_key_exists('service_level', $input) && is_array($input['service_level'])) {
            $sl = [];
            foreach (['A', 'B', 'C'] as $tier) {
                $p = (float) ($input['service_level'][$tier]['level'] ?? 0.90);
                if ($p < 0.50 || $p >= 0.999) {
                    throw new InvalidArgumentException("Service level {$tier} harus antara 0.50 (50%) dan 0.999 (99.9%), bernilai: {$p}");
                }
                $sl[$tier] = [
                    'level'   => $p,
                    'z_score' => $this->calculateZScore($p),
                ];
            }
            $sl['default'] = $sl['C'];
            $validated['service_level'] = $sl;
        }

        if (array_key_exists('target_cover_days', $input) && is_array($input['target_cover_days'])) {
            $tcd = [];
            foreach (['A', 'B', 'C'] as $tier) {
                $days = (int) ($input['target_cover_days'][$tier] ?? 30);
                if ($days < 1 || $days > 365) {
                    throw new InvalidArgumentException("Target cover days {$tier} harus antara 1 dan 365 hari, bernilai: {$days}");
                }
                $tcd[$tier] = $days;
            }
            $tcd['default'] = $tcd['C'];
            $validated['target_cover_days'] = $tcd;
        }

        return $validated;
    }

    /**
     * Hitung nilai Z dari probabilitas service level menggunakan aproksimasi Beasley-Springer-Moro.
     */
    public function calculateZScore(float $p): float
    {
        if ($p <= 0.0 || $p >= 1.0) {
            throw new InvalidArgumentException("Probabilitas harus antara 0 dan 1, bernilai: {$p}");
        }

        // Simetri distribusi normal standar
        if ($p < 0.5) {
            return -$this->calculateZScore(1.0 - $p);
        }

        // Aproksimasi Rasional Abramowitz & Stegun
        $t = sqrt(-2.0 * log(1.0 - $p));
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;

        $numerator = $c0 + ($c1 * $t) + ($c2 * ($t ** 2));
        $denominator = 1.0 + ($d1 * $t) + ($d2 * ($t ** 2)) + ($d3 * ($t ** 3));

        return $t - ($numerator / $denominator);
    }
}
