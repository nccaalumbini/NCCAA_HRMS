<?php

namespace App\Support;

final class NepaliPhoneNumber
{
    /**
     * Normalize a Nepali phone number to a canonical national format (digits only,
     * optional leading country code stripped) so duplicates match across sources.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '977') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        }

        return $digits;
    }
}
