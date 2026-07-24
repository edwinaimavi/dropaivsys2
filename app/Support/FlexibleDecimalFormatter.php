<?php

namespace App\Support;

class FlexibleDecimalFormatter
{
    public static function format($value, int $minDecimals = 2, int $maxDecimals = 6): string
    {
        $minDecimals = max(0, $minDecimals);
        $maxDecimals = max($minDecimals, $maxDecimals);
        $formatted = number_format((float) $value, $maxDecimals, '.', '');

        if ($maxDecimals > $minDecimals) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, '.');
            $currentDecimals = str_contains($formatted, '.')
                ? strlen(substr(strrchr($formatted, '.'), 1))
                : 0;

            if ($currentDecimals < $minDecimals) {
                $formatted .= $currentDecimals === 0 ? '.' : '';
                $formatted .= str_repeat('0', $minDecimals - $currentDecimals);
            }
        }

        return $formatted;
    }
}
