<?php
declare(strict_types=1);

final class Formatter
{
    public static function price(?float $value): string
    {
        if ($value === null) return '';
        return number_format($value, 2, ',', '.') . ' €';
    }

    public static function percentDiscount(?float $old, ?float $now): ?int
    {
        if ($old === null || $now === null) return null;
        if ($old <= 0 || $now >= $old) return null;
        return (int)round((1 - ($now / $old)) * 100);
    }
}