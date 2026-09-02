{{--
    COMPASS — Theme Switcher (shared across all role Settings pages)
    Drives window.CompassTheme (resources/js/app.js): applies the chosen
    theme instantly, persists to localStorage AND the database, and keeps the
    server preference (theme-preference meta) in sync.
--}}
<div class="theme-switcher" id="themeSwitcher">
    <style>
        .theme-switcher .theme-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .theme-switcher .theme-opt {
            border: 2px solid var(--border-color, #E5E7EB);
            border-radius: 14px;
            padding: 14px 10px;
            background: var(--bg-input, #fff);
            color: var(--text-primary, #1F2937);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            font-size: 13px;
            font-weight: 600;
        }
        .theme-switcher .theme-opt:hover { border-color: var(--brand-500, #04A052); }
        .theme-switcher .theme-opt.active {
            border-color: var(--brand-500, #04A052);
            background: rgba(4, 160, 82, 0.10);
            color: var(--brand-600, #038A45);
        }
        .theme-switcher .theme-opt .ico {
            width: 40px; height: 40px; margin: 0 auto 6px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 18px; border: 1px solid var(--border-color, #E5E7EB);
        }
        .theme-switcher .opt-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 0; border-bottom: 1px solid var(--border-light, #F3F4F6); gap: 16px;
        }
        .theme-switcher .opt-row:last-of-type { border-bottom: none; }
        .theme-switcher .opt-row .label { font-weight: 600; font-size: 14px; color: var(--text-primary, #1F2937); }
        .theme-switcher .opt-row .desc { font-size: 12px; color: var(--text-secondary, #6B7280); }
        .theme-switcher .font-radios { display: flex; gap: 10px; margin-top: 6px; }
        .theme-switcher .font-radios label {
            border: 1px solid var(--border-color, #E5E7EB); border-radius: 10px;
            padding: 6px 14px; cursor: pointer; font-size: 13px; color: var(--text-secondary, #6B7280);
        }
        .theme-switcher .font-radios input:checked + span,
        .theme-switcher .font-radios label:has(input:checked) {
            border-color: var(--brand-500, #04A052); color: var(--brand-600, #038A45); background: rgba(4,160,82,0.08);
        }
        .theme-switcher .save-btn {
            margin-top: 16px; background: linear-gradient(135deg, var(--brand-500, #04A052), var(--brand-600, #038A45));
            color: #fff; border: none; border-radius: 12px; padding: 10px 20px; font-weight: 600;
            cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;
        }
        .theme-switcher .save-btn:hover { transform: translateY(-1px); }

        /* Self-contained toggle (host pages style .switch inconsistently) */
        .theme-switcher .switch { position: relative; display: inline-block; width: 46px; height: 25px; flex-shrink: 0; }
        .theme-switcher .switch input { opacity: 0; width: 0; height: 0; }
        .theme-switcher .switch .slider {
            position: absolute; cursor: pointer; inset: 0;
            background: var(--border-color, #D1D5DB); border-radius: 25px; transition: 0.3s;
        }
        .theme-switcher .switch .slider::before {
            content: ''; position: absolute; width: 19px; height: 19px; left: 3px; bottom: 3px;
            background: #fff; border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .theme-switcher .switch input:checked + .slider { background: var(--brand-500, #04A052); }
        .theme-switcher .switch input:checked + .slider::before { transform: translateX(21px); }
    </style>

    <div class="theme-options">
        <div class="theme-opt" data-theme-value="light" role="button" tabindex="0">
            <div class="ico" style="background:#fff;"><i class="fas fa-sun text-yellow-500"></i></div>
            Light
        </div>
        <div class="theme-opt" data-theme-value="dark" role="button" tabindex="0">
            <div class="ico" style="background:#0F172A;color:#cbd5e1;"><i class="fas fa-moon"></i></div>
            Dark
        </div>
        <div class="theme-opt" data-theme-value="system" role="button" tabindex="0">
            <div class="ico" style="background:linear-gradient(135deg,#fff,#0F172A);color:#64748b;"><i class="fas fa-desktop"></i></div>
            System
        </div>
    </div>

    <div class="opt-row">
        <div>
            <div class="label">High contrast</div>
            <div class="desc">Stronger colors for better readability</div>
        </div>
        <label class="switch">
            <input type="checkbox" id="tsHighContrast">
            <span class="slider"></span>
        </label>
    </div>

    <div class="opt-row">
        <div>
            <div class="label">Reduced motion</div>
            <div class="desc">Disable non-essential animations</div>
        </div>
        <label class="switch">
            <input type="checkbox" id="tsReducedMotion">
            <span class="slider"></span>
        </label>
    </div>

    <div class="opt-row">
        <div>
            <div class="label">Font size</div>
            <div class="desc">Adjust text size across COMPASS</div>
        </div>
        <div class="font-radios" id="tsFontRadios">
            @foreach(['small' => 'A', 'medium' => 'A', 'large' => 'A'] as $size => $glyph)
                <label><input type="radio" name="ts_font" value="{{ $size }}" class="sr-only">
                    <span style="font-size:{{ $size === 'small' ? 13 : ($size === 'large' ? 18 : 15) }}px;">{{ $glyph }} · {{ ucfirst($size) }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <button type="button" class="save-btn" id="tsSave">
        <i class="fas fa-save mr-1"></i> Save appearance
    </button>

    <script>
        (function () {
            var T = window.CompassTheme;
            if (!T) return;

            var switcher = document.getElementById('themeSwitcher');
            if (!switcher) return;

            var opts = switcher.querySelectorAll('.theme-opt');
            var hc = switcher.querySelector('#tsHighContrast');
            var rm = switcher.querySelector('#tsReducedMotion');
            var fontWrap = switcher.querySelector('#tsFontRadios');
            var save = switcher.querySelector('#tsSave');

            function currentTheme() { return T.current(); }

            function refresh() {
                var theme = currentTheme();
                opts.forEach(function (o) {
                    o.classList.toggle('active', o.dataset.themeValue === theme);
                });

                try {
                    var prefs = JSON.parse(localStorage.getItem('compass_prefs') || 'null') || {};
                    if (!prefs.high_contrast) {
                        try {
                            var mp = JSON.parse(document.querySelector('meta[name="theme-prefs"]')?.content || '{}');
                            prefs = Object.assign(mp, prefs);
                        } catch (e) {}
                    }
                    hc.checked = !!prefs.high_contrast;
                    rm.checked = !!prefs.reduced_motion;
                    var fs = prefs.font_size || 'medium';
                    var r = fontWrap.querySelector('input[value="' + fs + '"]');
                    if (r) r.checked = true;
                } catch (e) {}
            }

            function setTheme(value) {
                T.set(value);
                refresh();
                if (window.showToast) window.showToast('Theme set to ' + value + ' mode', 'info');
            }

            opts.forEach(function (o) {
                o.addEventListener('click', function () { setTheme(o.dataset.themeValue); });
                o.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setTheme(o.dataset.themeValue); }
                });
            });

            save.addEventListener('click', function () {
                var font = (fontWrap.querySelector('input[name="ts_font"]:checked') || {}).value || 'medium';
                T.setAppearance({
                    theme: currentTheme(),
                    high_contrast: hc.checked,
                    reduced_motion: rm.checked,
                    font_size: font,
                });
                if (window.showToast) window.showToast('Appearance saved', 'success');
            });

            document.addEventListener('compass:theme', refresh);
            refresh();
        })();
    </script>
</div>
