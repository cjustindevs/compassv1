<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
    /**
     * SQL fragment computing the difference between two datetime columns in
     * seconds (colA - colB). Works on PostgreSQL, SQLite and MySQL.
     */
    public static function secondsBetween(string $colA, string $colB): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$colA} - {$colB}))",
            'mysql' => "TIMESTAMPDIFF(SECOND, {$colB}, {$colA})",
            default => "(julianday({$colA}) - julianday({$colB})) * 86400",
        };
    }

    /**
     * SQL fragment producing a 'YYYY-MM' month key from a datetime expression.
     */
    public static function monthKey(string $expr): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$expr}, 'YYYY-MM')",
            'mysql' => "DATE_FORMAT({$expr}, '%Y-%m')",
            default => "strftime('%Y-%m', {$expr})",
        };
    }

    /**
     * SQL fragment truncating a datetime expression to the first day of its month.
     */
    public static function monthStart(string $expr): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "DATE_TRUNC('month', {$expr})",
            'mysql' => "DATE_FORMAT({$expr}, '%Y-%m-01')",
            default => "date({$expr}, 'start of month')",
        };
    }

    /**
     * SQL fragment for a conditional aggregate count (COUNT(*) FILTER on pgsql,
     * SUM(CASE ...) elsewhere).
     */
    public static function countFilter(string $condition): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "COUNT(*) FILTER (WHERE {$condition})",
            default => "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END)",
        };
    }
}