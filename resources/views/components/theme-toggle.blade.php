{{--
    COMPASS — Dark mode toggle (global)
    Drop @include('components.theme-toggle') into any sidebar / navbar.
    Persists choice in localStorage and honours the OS preference on
    first visit. A no-flash snippet runs immediately on render.
--}}
@once
<script>
    (function () {
        try {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
</script>
@endonce

<button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
    <i class="fas fa-moon" id="themeIcon"></i>
</button>

<style>
    .theme-toggle {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        border: 1px solid var(--border-color, #E5E7EB);
        background: var(--bg-card, #ffffff);
        color: var(--text-primary, #1F2937);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.25s ease;
        margin-left: 6px;
        flex-shrink: 0;
    }
    .theme-toggle:hover {
        transform: scale(1.06);
        border-color: #04A052;
        color: #04A052;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) return;
        var icon = document.getElementById('themeIcon');

        function updateIcon(theme) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        updateIcon(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

        toggle.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateIcon(next);
            if (window.showToast) window.showToast('Theme set to ' + next + ' mode', 'info');
        });
    });
</script>
