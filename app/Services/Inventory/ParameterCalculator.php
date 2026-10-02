<?php

namespace App\Services\Inventory;

use InvalidArgumentException;

/**
 * ParameterCalculator — Kalkulasi Safety Stock (SS), Reorder Point (ROP), dan Max Stock (MAX)
 * sesuai spesifikasi Tahap 4.
 *
 * Rumus Standar Supply Chain Management:
 *   LT       = lead_time_days
 *   sigma_LT = lead_time_std_days
 *   Z        = inverse normal CDF berdasarkan service level kelas ABC
 *
 *   SS       = ceil( Z * sqrt( LT * sigma_daily^2 + mu_daily^2 * sigma_LT^2 ) )
 *   ROP      = ceil( mu_daily * LT + SS )
 *   Q_target = mu_daily * target_cover_days(kelas ABC)
 *   MAX      = ceil( ROP + Q_target )
 *
 * Semua nilai dijamin >= 0, integer (ceil), dan finite.
 */
class ParameterCalculator
{
    /**
     * Hitung parameter persediaan dari forecast output dan profil item.
     *
     * @param float $muDaily Demand rata-rata harian hasil forecast
     * @param float $sigmaDaily Deviasi standar residual forecast harian
     * @param float $leadTimeDays Lead time pemasok dalam hari
     * @param float $leadTimeStdDays Deviasi standar lead time dalam hari
     * @param string $abcClass Klasifikasi ABC ('A', 'B', 'C')
     * @return array{proposed_ss: int, proposed_rop: int, proposed_max: int, q_target: float, z_score: float}
     *
     * @throws InvalidArgumentException Jika nilai input negatif atau non-finite
     */
    public function calculate(
        float $muDaily,
        float $sigmaDaily,
        float $leadTimeDays,
        float $leadTimeStdDays = 0.0,
        string $abcClass = 'C'
    ): array {
        if (!is_finite($muDaily) || $muDaily < 0) {
            throw new InvalidArgumentException("mu_daily must be non-negative and finite, got: {$muDaily}");
        }

        if (!is_finite($sigmaDaily) || $sigmaDaily < 0) {
            throw new InvalidArgumentException("sigma_daily must be non-negative and finite, got: {$sigmaDaily}");
        }

        if (!is_finite($leadTimeDays) || $leadTimeDays < 0) {
            throw new InvalidArgumentException("lead_time_days must be non-negative and finite, got: {$leadTimeDays}");
        }

        if (!is_finite($leadTimeStdDays) || $leadTimeStdDays < 0) {
            throw new InvalidArgumentException("lead_time_std_days must be non-negative and finite, got: {$leadTimeStdDays}");
        }

        $zScore = $this->getZScore($abcClass);
        $coverDays = $this->getTargetCoverDays($abcClass);

        // SS = Z * sqrt( LT * sigma_daily^2 + mu_daily^2 * sigma_LT^2 )
        $variance = ($leadTimeDays * ($sigmaDaily ** 2)) + (($muDaily ** 2) * ($leadTimeStdDays ** 2));
        $ssRaw = $zScore * sqrt(max(0.0, $variance));
        $ss = (int) ceil(max(0.0, $ssRaw));

        // ROP = mu_daily * LT + SS
        $ropRaw = ($muDaily * $leadTimeDays) + $ss;
        $rop = (int) ceil(max(0.0, $ropRaw));

        // Q_target = mu_daily * target_cover_days
        $qTarget = max(0.0, $muDaily * $coverDays);

        // MAX = ROP + Q_target
        $maxRaw = $rop + $qTarget;
        $maxStock = (int) ceil(max(0.0, $maxRaw));

        return [
            'proposed_ss'   => $ss,
            'proposed_rop'  => $rop,
            'proposed_max'  => $maxStock,
            'q_target'      => $qTarget,
            'z_score'       => $zScore,
        ];
    }

    public function __construct(
        protected ?\App\Services\Settings\SettingsService $settings = null
    ) {
        $this->settings = $this->settings ?? app(\App\Services\Settings\SettingsService::class);
    }

    /**
     * Dapatkan Z-score berdasarkan service level kelas ABC melalui SettingsService.
     */
    public function getZScore(string $abcClass): float
    {
        return $this->settings->getZScore($abcClass);
    }

    /**
     * Dapatkan target cover days berdasarkan kelas ABC melalui SettingsService.
     */
    public function getTargetCoverDays(string $abcClass): int
    {
        return $this->settings->getCoverDaysForClass($abcClass);
    }
}
