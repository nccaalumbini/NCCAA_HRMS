<?php

namespace App\Support;

use DateTimeInterface;

class BikramSambat
{
    /**
     * The BS year for a given date. Nepali New Year falls on 14 April, so the
     * offset is +57 from and including that day, and +56 before it.
     */
    public static function year(DateTimeInterface|string|null $date = null): int
    {
        $date = $date === null ? now() : (\is_string($date) ? new \DateTimeImmutable($date) : $date);

        $offset = ($date->format('n') > 4) || ($date->format('n') === '4' && (int) $date->format('j') >= 14) ? 57 : 56;

        return ((int) $date->format('Y')) + $offset;
    }

    /**
     * The BS years from the current year down to a historical floor, newest first.
     *
     * @return array<int, string>
     */
    public static function yearRange(int $count = 20): array
    {
        $current = self::year();

        return array_map('strval', range($current, $current - max($count - 1, 0)));
    }
}
