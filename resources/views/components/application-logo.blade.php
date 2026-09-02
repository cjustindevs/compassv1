<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
    {{-- Outer ring --}}
    <circle cx="50" cy="50" r="47" fill="currentColor" opacity="0.08"/>
    <circle cx="50" cy="50" r="37" fill="none" stroke="currentColor" stroke-width="4"/>
    <circle cx="50" cy="50" r="34" fill="none" stroke="currentColor" stroke-width="1" opacity="0.5"/>

    {{-- Cardinal ticks --}}
    <path d="M50 6 L53 18 L47 18 Z" fill="currentColor"/>
    <path d="M50 94 L53 82 L47 82 Z" fill="currentColor"/>
    <path d="M6 50 L18 53 L18 47 Z" fill="currentColor"/>
    <path d="M94 50 L82 53 L82 47 Z" fill="currentColor"/>

    {{-- Needle (North) --}}
    <path d="M50 22 L59 80 L50 72 L41 80 Z" fill="currentColor"/>
    {{-- Needle (South) --}}
    <path d="M50 78 L59 20 L50 28 L41 20 Z" fill="currentColor" opacity="0.4"/>

    {{-- Center pivot --}}
    <circle cx="50" cy="50" r="4" fill="currentColor"/>
</svg>
