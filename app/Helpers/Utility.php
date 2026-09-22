<?php

namespace App\Helpers;

/**
 * Common Utility & Formatting Helpers
 */
class Utility
{
    /**
     * Format number as Indonesian Rupiah
     */
    public static function formatRupiah(float|int $amount, bool $prefix = true): string
    {
        $res = number_format($amount, 0, ',', '.');
        return $prefix ? "Rp {$res}" : $res;
    }

    /**
     * Convert speed with unit to bps (e.g. 10, 'M' -> 10000000)
     */
    public static function speedToBps(float $val, string $unit): int
    {
        $mul = ['k' => 1_000, 'M' => 1_000_000, 'G' => 1_000_000_000];
        return (int)round($val * ($mul[$unit] ?? 1_000_000));
    }

    /**
     * Convert bps to readable string (e.g. 10000000 -> 10M)
     */
    public static function bpsToStr(float|int $bps): string
    {
        if ($bps >= 1_000_000_000) {
            return round($bps / 1_000_000_000, 1) . 'G';
        }
        if ($bps >= 1_000_000) {
            return round($bps / 1_000_000, 1) . 'M';
        }
        if ($bps >= 1_000) {
            return round($bps / 1_000, 1) . 'k';
        }
        return (string)$bps;
    }

    /**
     * Format bytes to readable string (B, KB, MB, GB, TB)
     */
    public static function formatBytes(float|int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format date
     */
    public static function formatDate(?string $datetime, string $format = 'd M Y H:i'): string
    {
        if (!$datetime) {
            return '-';
        }
        return date($format, strtotime($datetime));
    }
}

