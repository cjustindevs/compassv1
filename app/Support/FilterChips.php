<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Builds removable filter chips from the current query string. */
final class FilterChips
{
    /**
     * @param  array<string,string>  $labels  query key => human label
     * @param  array<int,string>  $keep  keys never removed (kept in every href)
     * @return array<int,array{label:string,href:string}>
     */
    public static function from(Request $request, array $labels, array $keep = []): array
    {
        $chips = [];
        foreach ($labels as $key => $label) {
            $value = $request->query($key);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $text = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            $query = $request->except($key);
            $chips[] = [
                'label' => "{$label}: {$text}",
                'href' => url()->current().($query ? '?'.http_build_query($query) : ''),
            ];
        }

        return $chips;
    }
}
