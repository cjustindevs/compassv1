<?php

namespace App\Support;

/**
 * Display helpers for 1–5 scores (competency, normalized satisfaction).
 *
 * Panel feedback: show the score as a plain number ("4.2"), and state the 1–5 range once in
 * the label or caption instead of repeating "/5" after every value. Calculations are unchanged;
 * this only formats values that are already on the 1–5 scale.
 */
final class ScoreFormat
{
    public const SCALE = 'Scale 1–5';

    /** "4.2" for a valid score, "—" when there is no data. */
    public static function number(int|float|string|null $score, int $decimals = 1): string
    {
        if ($score === null || $score === '' || ! is_numeric($score)) {
            return '—';
        }

        return number_format((float) $score, $decimals);
    }

    /** Label suffix, e.g. "Competency score (Scale 1–5)". */
    public static function label(string $label): string
    {
        return $label.' ('.self::SCALE.')';
    }

    /** Normalize a legacy 1–10 Seeker rating to the 1–5 display scale (AdviserAnalytics::rating). */
    public static function fromTen(int|float|string|null $score): ?float
    {
        return is_numeric($score) ? \App\Services\AdviserAnalytics::rating((float) $score) : null;
    }
}
