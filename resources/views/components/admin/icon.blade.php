@props(['name', 'size' => 20, 'strokeWidth' => 1.8])

<svg
    {{ $attributes->merge(['class' => 'admin-icon']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
            @break

        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break

        @case('user-check')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="m16 11 2 2 4-4" />
            @break

        @case('user-plus')
            <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="8" cy="7" r="4" />
            <path d="M19 8v6M22 11h-6" />
            @break

        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5" />
            <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
            @break

        @case('download')
            <path d="M12 4v12M7 11l5 5 5-5" />
            <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
            @break

        @case('file-spreadsheet')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6" />
            <path d="M8 13h8M8 17h8M11 13v4" />
            @break

        @case('printer')
            <path d="M6 9V2h12v7" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <path d="M6 14h12v8H6z" />
            @break

        @case('bookmark')
            <path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1Z" />
            @break

        @case('filter')
            <path d="M4 4h16l-6 7v7l-4 2v-9Z" />
            @break

        @case('play-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m10 8 6 4-6 4Z" />
            @break

        @case('phone')
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.28-1.28a2 2 0 0 1 2.11-.45c.9.33 1.84.56 2.8.69A2 2 0 0 1 22 16.92Z" />
            @break

        @case('copy')
            <rect x="9" y="9" width="11" height="11" rx="2" />
            <path d="M15 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h3" />
            @break

        @case('undo')
            <path d="M9 7 4 12l5 5" />
            <path d="M4 12h9a7 7 0 1 1-6.5 9.6" />
            @break

        @case('save')
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" />
            <path d="M17 21v-8H7v8M7 3v5h8" />
            @break

        @case('megaphone')
            <path d="m3 11 18-5v12L3 13Z" />
            <path d="M11.6 15.4 13 21H7l-1.7-7" />
            @break

        @case('audit-log')
            <path d="M9 4h6M9 2h6v4H9Z" />
            <path d="M7 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2" />
            <path d="M8 11h8M8 15h8M8 19h5" />
            @break

        @case('sliders')
            <path d="M4 6h10M18 6h2M4 12h3M11 12h9M4 18h8M16 18h4" />
            <circle cx="16" cy="6" r="2" />
            <circle cx="9" cy="12" r="2" />
            <circle cx="14" cy="18" r="2" />
            @break

        @case('inbox')
            <path d="M4 4h16l2 11v5H2v-5Z" />
            <path d="M2 15h5l2 3h6l2-3h5" />
            @break

        @case('clipboard')
            <path d="M9 4h6M9 2h6v4H9Z" />
            <path d="M7 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2" />
            <path d="M8 11h8M8 15h6" />
            @break

        @case('folder-check')
            <path d="M3 5h6l2 2h10v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
            <path d="m9 14 2 2 4-4" />
            @break

        @case('more-horizontal')
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="19" cy="12" r="1" fill="currentColor" stroke="none" />
            @break

        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
            <circle cx="12" cy="12" r="2.5" />
            @break

        @case('edit')
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" />
            @break

        @case('key')
            <circle cx="8" cy="15" r="4" />
            <path d="m11 12 8-8M16 7l2 2M14 9l2 2" />
            @break

        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41" />
            @break

        @case('moon')
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
            @break

        @case('monitor')
            <rect x="2" y="3" width="20" height="14" rx="2" />
            <path d="M8 21h8M12 17v4" />
            @break

        @case('globe')
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
            @break

        @case('memory')
            <rect x="3" y="7" width="18" height="10" rx="2" />
            <path d="M7 10v4M10 10v4M13 10v4M16 10v4M6 4v3M10 4v3M14 4v3M18 4v3M6 17v3M10 17v3M14 17v3M18 17v3" />
            @break

        @case('layers')
            <path d="m12 2 9 5-9 5-9-5Z" />
            <path d="m3 12 9 5 9-5M3 17l9 5 9-5" />
            @break

        @case('user-minus')
            <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="8" cy="7" r="4" />
            <path d="M17 11h6" />
            @break

        @case('role')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            @break

        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            <path d="m9 12 2 2 4-4" />
            @break

        @case('book-open')
            <path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2Z" />
            <path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7Z" />
            @break

        @case('file-text')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6M8 13h8M8 17h6" />
            @break

        @case('backup')
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v6c0 1.66 3.58 3 8 3 1.16 0 2.27-.09 3.25-.26" />
            <path d="M4 11v6c0 1.66 3.58 3 8 3M20 5v5" />
            <path d="M18 15v3h3M21 18a4 4 0 1 1-1.17-2.83" />
            @break

        @case('activity')
            <path d="M3 12h4l2-7 4 14 2-7h6" />
            @break

        @case('chart-line')
            <path d="M3 3v18h18" />
            <path d="m7 16 4-5 4 3 5-7" />
            @break

        @case('settings')
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09a1.7 1.7 0 0 0-1.1-1.51 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H3v-4h.09A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V3h4v.09A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.39.26.75.6 1 1 .22.34.35.72.4 1.1v.1H21v4h-.09A1.7 1.7 0 0 0 19.4 15Z" />
            @break

        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3" />
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            @break

        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break

        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break

        @case('panel-left')
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M9 4v16" />
            @break

        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-4-4" />
            @break

        @case('help')
            <circle cx="12" cy="12" r="9" />
            <path d="M9.6 9a2.55 2.55 0 0 1 4.95.85c0 1.7-2.55 2.15-2.55 3.45M12 17h.01" />
            @break

        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" />
            @break

        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break

        @case('heart-pulse')
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z" />
            <path d="M7 12h2l1.5-3 3 6 1.5-3h2" />
            @break

        @case('storage')
            <rect x="3" y="4" width="18" height="7" rx="2" />
            <rect x="3" y="13" width="18" height="7" rx="2" />
            <path d="M7 7.5h.01M7 16.5h.01" />
            @break

        @case('server')
            <rect x="3" y="3" width="18" height="7" rx="2" />
            <rect x="3" y="14" width="18" height="7" rx="2" />
            <path d="M7 6.5h.01M7 17.5h.01" />
            @break

        @case('database')
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v7c0 1.66 3.58 3 8 3s8-1.34 8-3V5" />
            <path d="M4 12v7c0 1.66 3.58 3 8 3s8-1.34 8-3v-7" />
            @break

        @case('network')
            <rect x="9" y="2" width="6" height="5" rx="1" />
            <rect x="2" y="17" width="6" height="5" rx="1" />
            <rect x="16" y="17" width="6" height="5" rx="1" />
            <path d="M12 7v5M5 17v-5h14v5" />
            @break

        @case('arrow-up')
            <path d="m18 15-6-6-6 6" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break

        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break

        @case('warning')
            <path d="m10.3 3.4-8.1 14a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3l-8.1-14a2 2 0 0 0-3.4 0Z" />
            <path d="M12 9v4M12 17h.01" />
            @break

        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8 12 2.6 2.6L16.5 9" />
            @break

        @case('alert-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v5M12 17h.01" />
            @break

        @case('cpu')
            <rect x="5" y="5" width="14" height="14" rx="2" />
            <rect x="9" y="9" width="6" height="6" rx="1" />
            <path d="M9 1v4M15 1v4M9 19v4M15 19v4M19 9h4M19 14h4M1 9h4M1 14h4" />
            @break

        @default
            <circle cx="12" cy="12" r="9" />
    @endswitch
</svg>
