@props(['topic' => 'more', 'size' => 'card'])
{{--
    Decorative topic illustration for the self-help library (panel comment: text alone is hard to read).
    Drawn inline so it needs no image files, works offline in the PWA, and contains no emoji or icon fonts.
    Purely decorative: hidden from screen readers.
--}}
@php
    $themes = [
        'breathing'   => ['#e6f2fb', '#b9daf2', '#5b9fd6', '#2f6fa8'],
        'grounding'   => ['#eef6e8', '#c9e2b5', '#7fb069', '#4a7a3a'],
        'stress'      => ['#e5f5f2', '#b5e2da', '#4fb3a3', '#24796d'],
        'mindfulness' => ['#f1edfa', '#d9cff3', '#9b86d6', '#6450a8'],
        'journaling'  => ['#fbf3e6', '#f1dcb4', '#d6a35b', '#9a6a26'],
        'coping'      => ['#fdeee9', '#f6cdbf', '#e2846a', '#a64b33'],
        'sleep'       => ['#e9ecf8', '#c4cbee', '#6f7fcf', '#3c4a94'],
        'anxiety'     => ['#e7f4f6', '#bfe1e7', '#5aa9b8', '#2d7280'],
        'more'        => ['#eaf8f0', '#bfe8cf', '#3fb672', '#027039'],
    ];
    [$bg, $soft, $mid, $deep] = $themes[$topic] ?? $themes['more'];
@endphp
<svg {{ $attributes->merge(['class' => 'sh-art sh-art-'.$size]) }} viewBox="0 0 320 120" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false" role="presentation">
    <rect width="320" height="120" fill="{{ $bg }}"/>
    @switch($topic)
        @case('breathing')
            <circle cx="160" cy="60" r="52" fill="{{ $soft }}" opacity=".55" class="sh-breathe"/>
            <circle cx="160" cy="60" r="36" fill="{{ $soft }}"/>
            <circle cx="160" cy="60" r="20" fill="{{ $mid }}"/>
            <path d="M20 96 C 70 80, 110 110, 160 96 S 250 80, 300 96" fill="none" stroke="{{ $mid }}" stroke-width="3" stroke-linecap="round" opacity=".5"/>
            @break
        @case('grounding')
            <circle cx="248" cy="38" r="16" fill="{{ $soft }}"/>
            <path d="M0 92 Q 70 50 140 86 T 320 74 V120 H0 Z" fill="{{ $soft }}"/>
            <path d="M0 104 Q 90 76 180 100 T 320 96 V120 H0 Z" fill="{{ $mid }}"/>
            <rect x="74" y="64" width="4" height="24" rx="2" fill="{{ $deep }}"/>
            <circle cx="76" cy="60" r="11" fill="{{ $mid }}"/>
            @break
        @case('stress')
            <path d="M0 52 C 40 36, 80 68, 120 52 S 200 36, 240 52 S 300 68, 320 52" fill="none" stroke="{{ $soft }}" stroke-width="10" stroke-linecap="round"/>
            <path d="M0 74 C 40 58, 80 90, 120 74 S 200 58, 240 74 S 300 90, 320 74" fill="none" stroke="{{ $mid }}" stroke-width="6" stroke-linecap="round" opacity=".75"/>
            <path d="M0 96 C 40 86, 80 106, 120 96 S 200 86, 240 96 S 300 106, 320 96" fill="none" stroke="{{ $deep }}" stroke-width="3" stroke-linecap="round" opacity=".45"/>
            @break
        @case('mindfulness')
            <circle cx="160" cy="70" r="30" fill="{{ $soft }}"/>
            <ellipse cx="160" cy="78" rx="14" ry="26" fill="{{ $mid }}" opacity=".85"/>
            <ellipse cx="140" cy="82" rx="12" ry="22" fill="{{ $mid }}" opacity=".6" transform="rotate(-28 140 82)"/>
            <ellipse cx="180" cy="82" rx="12" ry="22" fill="{{ $mid }}" opacity=".6" transform="rotate(28 180 82)"/>
            <rect x="96" y="104" width="128" height="4" rx="2" fill="{{ $deep }}" opacity=".35"/>
            @break
        @case('journaling')
            <rect x="104" y="18" width="112" height="92" rx="8" fill="#fffdf8" stroke="{{ $soft }}" stroke-width="2"/>
            @foreach([40, 56, 72, 88] as $y)<rect x="120" y="{{ $y }}" width="{{ $y === 88 ? 46 : 80 }}" height="4" rx="2" fill="{{ $soft }}"/>@endforeach
            <rect x="214" y="28" width="8" height="64" rx="4" fill="{{ $mid }}" transform="rotate(24 218 60)"/>
            @break
        @case('coping')
            <ellipse cx="62" cy="96" rx="34" ry="10" fill="{{ $soft }}"/>
            <ellipse cx="132" cy="82" rx="30" ry="9" fill="{{ $soft }}"/>
            <ellipse cx="196" cy="68" rx="26" ry="8" fill="{{ $mid }}" opacity=".7"/>
            <ellipse cx="252" cy="54" rx="22" ry="7" fill="{{ $mid }}"/>
            <circle cx="282" cy="30" r="10" fill="{{ $deep }}" opacity=".6"/>
            @break
        @case('sleep')
            <circle cx="210" cy="56" r="30" fill="{{ $soft }}"/>
            <circle cx="224" cy="46" r="28" fill="{{ $bg }}"/>
            @foreach([[70, 34, 3], [108, 62, 2], [140, 28, 2.5], [262, 92, 2], [52, 84, 2]] as [$x, $y, $r])<circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}" fill="{{ $mid }}"/>@endforeach
            <path d="M0 108 Q 160 92 320 108 V120 H0 Z" fill="{{ $soft }}" opacity=".7"/>
            @break
        @case('anxiety')
            <path d="M84 70 a22 22 0 0 1 30 -26 a28 28 0 0 1 52 6 a20 20 0 0 1 10 38 h-86 a18 18 0 0 1 -6 -18 z" fill="{{ $soft }}"/>
            <circle cx="236" cy="44" r="14" fill="{{ $mid }}" opacity=".55"/>
            <path d="M0 104 H320" stroke="{{ $mid }}" stroke-width="3" stroke-dasharray="2 10" stroke-linecap="round" opacity=".6"/>
            @break
        @default
            <circle cx="96" cy="58" r="30" fill="{{ $soft }}"/>
            <circle cx="170" cy="70" r="22" fill="{{ $mid }}" opacity=".55"/>
            <circle cx="232" cy="50" r="14" fill="{{ $deep }}" opacity=".35"/>
    @endswitch
</svg>
