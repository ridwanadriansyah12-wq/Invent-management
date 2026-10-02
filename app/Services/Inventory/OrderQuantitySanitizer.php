<?php

namespace App\Services\Inventory;

use InvalidArgumentException;

/**
 * OrderQuantitySanitizer — Sanitasi kuantitas pemesanan (Order Quantity).
 *
 * Sesuai spesifikasi Tahap 7:
 *   Final Q = max( MOQ , ceil( q_raw / LotSize ) * LotSize )
 *
 * Contoh Uji:
 *   MOQ = 50, LotSize = 12:
 *   - Q = 10  -> 50
 *   - Q = 60  -> 60
 *   - Q = 61  -> 72
 *   - Q = 130 -> 132
 */
class OrderQuantitySanitizer
{
    /**
     * Sanitasi kuantitas pesanan mentah (q_raw) terhadap MOQ dan Lot Size.
     *
     * @param float $qRaw Kuantitas mentah yang dihitung (effective_max - inventory_position)
     * @param float $moq Minimum Order Quantity (harus >= 1)
     * @param float $lotSize Ukuran batch kelipatan pemesanan (harus >= 1)
     * @return int Kuantitas pesanan final bulat yang telah disanitasi
     *
     * @throws InvalidArgumentException
     */
    public function sanitize(float $qRaw, float $moq = 1.0, float $lotSize = 1.0): int
    {
        $moq = max(1.0, $moq);
        $lotSize = max(1.0, $lotSize);

        if (!is_finite($qRaw)) {
            throw new InvalidArgumentException("q_raw must be finite, got: {$qRaw}");
        }

        if ($qRaw <= 0) {
            return (int) ceil($moq);
        }

        // Hitung kelipatan lot_size terdekat ke atas
        $lotMultiplier = (int) ceil($qRaw / $lotSize);
        $roundedByLot = $lotMultiplier * $lotSize;

        // Final Q = max( MOQ , ceil( q_raw / LotSize ) * LotSize )
        $finalQ = max($moq, $roundedByLot);

        return (int) ceil($finalQ);
    }
}
