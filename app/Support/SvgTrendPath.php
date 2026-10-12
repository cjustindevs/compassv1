<?php

namespace App\Support;

final class SvgTrendPath
{
    /** Smooth screen coordinates without moving points or overshooting their values. */
    public static function make(array $points): string
    {
        if ($points === []) return '';

        $number = fn ($value) => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        $pair = fn ($point) => $number($point[0]).' '.$number($point[1]);
        $path = 'M '.$pair($points[0]);
        $count = count($points);
        if ($count === 1) return $path;
        if ($count === 2) return $path.' L '.$pair($points[1]);

        $widths = $slopes = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $widths[$i] = $points[$i + 1][0] - $points[$i][0];
            // Render a straight path if coordinates are not ordered along the x-axis.
            if ($widths[$i] <= 0) return $path.' L '.implode(' L ', array_map($pair, array_slice($points, 1)));
            $slopes[$i] = ($points[$i + 1][1] - $points[$i][1]) / $widths[$i];
        }

        $tangents = [$slopes[0]];
        for ($i = 1; $i < $count - 1; $i++) {
            $before = $slopes[$i - 1];
            $after = $slopes[$i];
            if ($before * $after <= 0) {
                $tangents[$i] = 0;
            } else {
                $a = 2 * $widths[$i] + $widths[$i - 1];
                $b = $widths[$i] + 2 * $widths[$i - 1];
                $slope = ($a + $b) / ($a / $before + $b / $after);
                $tangents[$i] = ($slope < 0 ? -1 : 1) * min(abs($slope), 3 * min(abs($before), abs($after)));
            }
        }
        $tangents[$count - 1] = $slopes[$count - 2];

        for ($i = 0; $i < $count - 1; $i++) {
            $step = $widths[$i] / 3;
            $first = [$points[$i][0] + $step, $points[$i][1] + $step * $tangents[$i]];
            $second = [$points[$i + 1][0] - $step, $points[$i + 1][1] - $step * $tangents[$i + 1]];
            $path .= ' C '.$pair($first).' '.$pair($second).' '.$pair($points[$i + 1]);
        }

        return $path;
    }
}
