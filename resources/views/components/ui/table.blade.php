@props(['stack' => false])
<div {{ $attributes->merge(['class' => 'ui-table'.($stack ? ' ui-table--stack' : '')]) }}>
    <table>
        {{ $slot }}
    </table>
</div>
