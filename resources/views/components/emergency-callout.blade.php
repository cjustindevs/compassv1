@props(['compact' => false])
{{-- QA: "Add a message to call the hotline / please dial the emergency number." Numbers are tappable on phones. --}}
<aside {{ $attributes->merge(['class' => 'emergency-callout'.($compact ? ' is-compact' : '')]) }} role="note" aria-label="Emergency numbers">
    <p><strong>In danger right now?</strong> Dial <a href="tel:911">911</a> or call the NCMH crisis line <a href="tel:1553">1553</a> (free, 24/7). COMPASS is peer support, not an emergency service.</p>
    @unless($compact)<a class="ec-more" href="{{ route('emergency') }}">More hotlines</a>@endunless
</aside>
@once
<style>
.emergency-callout { display: flex; align-items: center; gap: 10px 16px; flex-wrap: wrap; background: #fff5f5; border: 1px solid #f5c2c2; border-left: 4px solid #d03b3b; border-radius: 12px; padding: 10px 14px; margin: 0 0 14px; }
.emergency-callout p { margin: 0; flex: 1 1 320px; font-size: 13px; line-height: 1.5; color: #7a1f1f; }
.emergency-callout a[href^="tel:"] { color: #b42318; font-weight: 800; text-decoration: underline; white-space: nowrap; }
.emergency-callout .ec-more { font-size: 13px; font-weight: 700; color: #b42318; text-decoration: none; border: 1px solid #f5c2c2; background: #fff; border-radius: 99px; padding: 5px 12px; white-space: nowrap; }
.emergency-callout.is-compact { padding: 8px 12px; margin: 8px 0 0; }
</style>
@endonce
