<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * InventoryParameterService
 *
 * Mengelola parameter ROP & Safety Stock dengan Decoupling Storage:
 * 1. In-Memory Cache (Redis) untuk fast lookup (latensi < 1ms)
 * 2. RDBMS Persistent Storage untuk audit trail & durability
 * 3. Human-in-the-Loop priority engine (Manual Override > Machine Learning)
 * 4. Graceful Degradation jika Redis offline / tidak terkonfigurasi
 */
class InventoryParameterService
{
    public const CACHE_PREFIX    = 'rop:';
    public const COOLDOWN_PREFIX = 'alert_cooldown:';
    public const CACHE_TTL       = 604800; // 7 hari dalam detik
    public const DEFAULT_COOLDOWN_TTL = 86400; // 24 jam dalam detik

    /**
     * Mengambil parameter efektif untuk item.
     * Alur: Cek Redis Hash -> Fallback ke Database jika Cache Miss / Redis Offline.
     */
    public function getEffectiveParameters(int $itemId): array
    {
        // 1. Coba baca dari Redis
        try {
            if ($this->isRedisAvailable()) {
                $cached = Redis::connection()->hgetall(self::CACHE_PREFIX . $itemId);

                if (!empty($cached) && isset($cached['effective_rop'])) {
                    return [
                        'item_id'       => $itemId,
                        'safety_stock'  => (float) $cached['effective_ss'],
                        'rop'           => (float) $cached['effective_rop'],
                        'is_override'   => (bool) ($cached['is_override'] ?? 0),
                        'ml_ss'         => (float) ($cached['ml_ss'] ?? 0),
                        'ml_rop'        => (float) ($cached['ml_rop'] ?? 0),
                        'manual_ss'     => !empty($cached['manual_ss']) ? (float) $cached['manual_ss'] : null,
                        'manual_rop'    => !empty($cached['manual_rop']) ? (float) $cached['manual_rop'] : null,
                        'updated_at'    => $cached['updated_at'] ?? null,
                        'source'        => 'redis',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("InventoryParameterService: Redis read failed for item {$itemId}, falling back to DB. Error: " . $e->getMessage());
        }

        // 2. Cache Miss atau Redis Offline: Baca langsung dari database
        $item = Item::find($itemId);
        if (!$item) {
            return [
                'item_id'      => $itemId,
                'safety_stock' => 0.0,
                'rop'          => 0.0,
                'is_override'  => false,
                'source'       => 'not_found',
            ];
        }

        // Write-through / repopulasi cache
        return $this->cacheParameters($item);
    }

    /**
     * Menyimpan snapshot parameter item ke dalam Redis Hash: rop:{item_id}
     * Menerapkan logika prioritas: Manual Override > ML Prediction.
     */
    public function cacheParameters(Item $item): array
    {
        $isOverride   = (bool) $item->is_manual_override;
        $mlSS         = (float) ($item->ml_safety_stock ?? $item->safety_stock ?? 0);
        $mlRop        = (float) ($item->ml_rop ?? $item->rop ?? 0);
        
        $manualSS     = $item->manual_safety_stock !== null ? (float) $item->manual_safety_stock : null;
        $manualRop    = $item->manual_rop !== null ? (float) $item->manual_rop : null;

        // Aturan Prioritas:
        $effectiveSS  = ($isOverride && $manualSS !== null) ? $manualSS : $mlSS;
        $effectiveRop = ($isOverride && $manualRop !== null) ? $manualRop : $mlRop;

        $payload = [
            'item_id'       => (string) $item->id,
            'ml_ss'         => (string) round($mlSS, 2),
            'ml_rop'        => (string) round($mlRop, 2),
            'is_override'   => $isOverride ? '1' : '0',
            'manual_ss'     => $manualSS !== null ? (string) round($manualSS, 2) : '',
            'manual_rop'    => $manualRop !== null ? (string) round($manualRop, 2) : '',
            'effective_ss'  => (string) round($effectiveSS, 2),
            'effective_rop' => (string) round($effectiveRop, 2),
            'updated_at'    => (string) now()->timestamp,
        ];

        try {
            if ($this->isRedisAvailable()) {
                $redis = Redis::connection();
                $key   = self::CACHE_PREFIX . $item->id;
                $redis->hmset($key, $payload);
                $redis->expire($key, self::CACHE_TTL);
            }
        } catch (\Throwable $e) {
            Log::warning("InventoryParameterService: Gagal menyimpan cache Redis untuk item {$item->id}: " . $e->getMessage());
        }

        return [
            'item_id'      => $item->id,
            'safety_stock' => $effectiveSS,
            'rop'          => $effectiveRop,
            'is_override'  => $isOverride,
            'ml_ss'        => $mlSS,
            'ml_rop'       => $mlRop,
            'manual_ss'    => $manualSS,
            'manual_rop'   => $manualRop,
            'updated_at'   => now()->timestamp,
            'source'       => 'database',
        ];
    }

    /**
     * Terapkan Manual Override oleh Tim Procurement (Human-in-the-Loop).
     */
    public function applyManualOverride(
        Item $item,
        float $manualRop,
        ?float $manualSS = null,
        ?string $reason = null,
        ?int $userId = null
    ): void {
        $item->update([
            'is_manual_override'  => true,
            'manual_rop'          => round($manualRop, 2),
            'manual_safety_stock' => $manualSS !== null ? round($manualSS, 2) : $item->safety_stock,
            'override_reason'     => $reason,
            'override_updated_by' => $userId ?? auth()->id(),
            'override_updated_at' => now(),
            // Sync fallback kolom legacy agar backward compatible
            'rop'                 => round($manualRop, 2),
            'safety_stock'        => $manualSS !== null ? round($manualSS, 2) : $item->safety_stock,
        ]);

        // Perbarui cache in-memory secara instan (Write-Through)
        $this->cacheParameters($item->fresh());
    }

    /**
     * Reset Manual Override dan kembalikan nilai ke hasil komputasi Machine Learning.
     */
    public function resetManualOverride(Item $item): void
    {
        $item->update([
            'is_manual_override'  => false,
            'override_reason'     => null,
            'override_updated_by' => null,
            'override_updated_at' => null,
            // Kembalikan nilai aktif ke ML output
            'rop'                 => (float) $item->ml_rop,
            'safety_stock'        => (float) $item->ml_safety_stock,
        ]);

        // Perbarui cache in-memory secara instan
        $this->cacheParameters($item->fresh());
    }

    /**
     * Periksa apakah notifikasi email untuk item ini sedang dalam masa cooldown.
     */
    public function isAlertInCooldown(int $itemId): bool
    {
        try {
            if ($this->isRedisAvailable()) {
                return (bool) Redis::connection()->exists(self::COOLDOWN_PREFIX . $itemId);
            }
        } catch (\Throwable $e) {
            Log::warning("InventoryParameterService: Gagal cek cooldown Redis untuk item {$itemId}: " . $e->getMessage());
        }

        // Fallback ke Laravel Cache Store jika Redis instance terpisah tidak aktif
        return \Illuminate\Support\Facades\Cache::has(self::COOLDOWN_PREFIX . $itemId);
    }

    /**
     * Set masa cooldown notifikasi email di Redis / Cache Store (Anti-Spam Throttling).
     */
    public function setAlertCooldown(int $itemId, int $ttlSeconds = self::DEFAULT_COOLDOWN_TTL): void
    {
        try {
            if ($this->isRedisAvailable()) {
                Redis::connection()->setex(
                    self::COOLDOWN_PREFIX . $itemId,
                    $ttlSeconds,
                    now()->toIso8601String()
                );
                return;
            }
        } catch (\Throwable $e) {
            Log::warning("InventoryParameterService: Gagal set cooldown Redis untuk item {$itemId}: " . $e->getMessage());
        }

        // Fallback ke Laravel Cache Store
        \Illuminate\Support\Facades\Cache::put(self::COOLDOWN_PREFIX . $itemId, now()->toIso8601String(), $ttlSeconds);
    }

    /**
     * Verifikasi ketersediaan koneksi Redis secara aman (Circuit Breaker check).
     */
    private function isRedisAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        try {
            // Cek apakah driver / class Redis tersedia
            if (!class_exists('Illuminate\Support\Facades\Redis')) {
                $available = false;
                return false;
            }

            Redis::connection()->ping();
            $available = true;
        } catch (\Throwable $e) {
            $available = false;
        }

        return $available;
    }
}
