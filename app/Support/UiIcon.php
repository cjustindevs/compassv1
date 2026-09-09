<?php
namespace App\Support;

final class UiIcon
{
    private const LEGACY = [
        "\u{23F0}" => "fa-clock",
        "\u{23F1}" => "fa-stopwatch",
        "\u{23F3}" => "fa-hourglass-half",
        "\u{23F8}" => "fa-pause",
        "\u{2605}" => "fa-star",
        "\u{2606}" => "fa-star",
        "\u{26A0}" => "fa-triangle-exclamation",
        "\u{26A1}" => "fa-bolt",
        "\u{2705}" => "fa-circle-check",
        "\u{2713}" => "fa-check",
        "\u{2715}" => "fa-xmark",
        "\u{274C}" => "fa-circle-xmark",
        "\u{1F305}" => "fa-sun",
        "\u{1F319}" => "fa-moon",
        "\u{1F31F}" => "fa-star",
        "\u{1F32C}" => "fa-wind",
        "\u{1F33F}" => "fa-leaf",
        "\u{1F389}" => "fa-star",
        "\u{1F399}" => "fa-microphone",
        "\u{1F3AC}" => "fa-video",
        "\u{1F3C1}" => "fa-flag-checkered",
        "\u{1F441}" => "fa-eye",
        "\u{1F448}" => "fa-arrow-left",
        "\u{1F465}" => "fa-users",
        "\u{1F499}" => "fa-heart",
        "\u{1F49A}" => "fa-heart",
        "\u{1F49C}" => "fa-heart",
        "\u{1F4AA}" => "fa-dumbbell",
        "\u{1F4AC}" => "fa-comments",
        "\u{1F4C4}" => "fa-file-lines",
        "\u{1F4C5}" => "fa-calendar",
        "\u{1F4CA}" => "fa-chart-column",
        "\u{1F4CB}" => "fa-clipboard-list",
        "\u{1F4D3}" => "fa-book",
        "\u{1F4D6}" => "fa-book-open",
        "\u{1F4D8}" => "fa-book",
        "\u{1F4DA}" => "fa-book-open",
        "\u{1F4DD}" => "fa-pen-to-square",
        "\u{1F4E2}" => "fa-bullhorn",
        "\u{1F4EC}" => "fa-envelope-open",
        "\u{1F504}" => "fa-arrows-rotate",
        "\u{1F50D}" => "fa-magnifying-glass",
        "\u{1F514}" => "fa-bell",
        "\u{1F516}" => "fa-bookmark",
        "\u{1F525}" => "fa-fire",
        "\u{1F534}" => "fa-circle",
        "\u{1F539}" => "fa-circle",
        "\u{1F550}" => "fa-clock",
        "\u{1F5D3}" => "fa-calendar-days",
        "\u{1F60A}" => "fa-face-laugh",
        "\u{1F60C}" => "fa-face-smile",
        "\u{1F610}" => "fa-face-meh",
        "\u{1F614}" => "fa-face-frown",
        "\u{1F61F}" => "fa-face-frown",
        "\u{1F622}" => "fa-face-sad-tear",
        "\u{1F630}" => "fa-face-frown",
        "\u{1F634}" => "fa-bed",
        "\u{1F642}" => "fa-face-smile",
        "\u{1F645}" => "fa-ban",
        "\u{1F6A8}" => "fa-triangle-exclamation",
        "\u{1F6D1}" => "fa-hand",
        "\u{1F6DF}" => "fa-life-ring",
        "\u{1F7E2}" => "fa-circle",
        "\u{1F91D}" => "fa-handshake",
        "\u{1F9D8}" => "fa-spa",
        "\u{1F9F0}" => "fa-toolbox",
        "\u{1FAA8}" => "fa-mountain",
        "\u{1FAC2}" => "fa-hands-holding-circle",
    ];

    public static function resolve(?string $value): string
    {
        $value = str_replace("\u{FE0F}", '', trim($value ?? ''));
        if (isset(self::LEGACY[$value])) return 'fas '.self::LEGACY[$value];
        if (preg_match('/^(?:(?:fas|far|fa-solid|fa-regular) )?fa-[a-z0-9-]+$/D', $value)) {
            return str_starts_with($value, 'fa-') && !str_contains($value, ' ') ? 'fas '.$value : $value;
        }
        return 'fas fa-circle-info';
    }
}
