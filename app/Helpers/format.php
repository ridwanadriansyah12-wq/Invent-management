<?php

if (!function_exists('format_number_id')) {
    /**
     * Format angka standar Indonesia:
     * - Titik sebagai pemisah ribuan
     * - Koma sebagai pemisah desimal
     * - Maksimal 3 desimal, membuang nol buntut (mis. 12.500 bukan 12500, 1.234,5 bukan 1234.500)
     */
    function format_number_id(float|int|string|null $value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $num = (float) $value;

        // Format dengan maxDecimals desimal
        $formatted = number_format($num, $maxDecimals, ',', '.');

        // Jika ada koma, buang nol buntut dan koma jika tidak ada sisa desimal
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }
}

if (!function_exists('format_volume_id')) {
    /**
     * Format angka volume dengan satuan m³ Indonesia.
     */
    function format_volume_id(float|int|string|null $value, int $maxDecimals = 3): string
    {
        return format_number_id($value, $maxDecimals) . ' m³';
    }
}

if (!function_exists('format_percent_id')) {
    /**
     * Format persentase standar Indonesia (contoh: 85,0%).
     */
    function format_percent_id(float|int|string|null $value, int $decimals = 1): string
    {
        if ($value === null || $value === '') {
            return '0%';
        }

        $pct = (float) $value;
        // Jika nilai dalam skala 0.0 - 1.0, konversikan ke 0 - 100%
        if ($pct > 0.0 && $pct <= 1.0) {
            $pct *= 100.0;
        }

        $formatted = number_format($pct, $decimals, ',', '.');
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted . '%';
    }
}

if (!function_exists('format_date_id')) {
    /**
     * Format tanggal Indonesia (contoh: 24 Sep 2026).
     */
    function format_date_id(mixed $date, string $format = 'd M Y'): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $carbon->locale('id')->translatedFormat($format);
    }
}

if (!function_exists('format_datetime_id')) {
    /**
     * Format tanggal dan jam Indonesia (contoh: 24 Sep 2026 14:30).
     */
    function format_datetime_id(mixed $date): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $carbon->locale('id')->translatedFormat('d M Y H:i');
    }
}
