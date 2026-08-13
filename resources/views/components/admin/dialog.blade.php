@props([
    'id',
    'title',
    'description' => null,
    'size' => 'medium',
    'openOnLoad' => false,
])

<dialog
    id="{{ $id }}"
    class="admin-dialog admin-dialog-{{ $size }}"
    data-admin-dialog
    @if ($openOnLoad) data-open-on-load @endif
    aria-labelledby="{{ $id }}-title"
    @if ($description) aria-describedby="{{ $id }}-description" @endif
>
    <div class="admin-dialog-panel">
        <header class="admin-dialog-header">
            <span>
                <h2 id="{{ $id }}-title">{{ $title }}</h2>
                @if ($description)
                    <p id="{{ $id }}-description">{{ $description }}</p>
                @endif
            </span>
            <button class="icon-button" type="button" data-dialog-close aria-label="Close {{ strtolower($title) }}">
                <x-admin.icon name="close" :size="20" />
            </button>
        </header>

        {{ $slot }}
    </div>
</dialog>
