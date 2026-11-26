{{--
    COMPASS × Project Dial-A-Friend brand lockup: the PDAF seal, the COMPASS
    wordmark and the partnership line. Used on the landing page and the
    sign-in / registration screens.

    Usage: <x-pdaf-lockup />  or  <x-pdaf-lockup size="sm" :href="null" />
--}}
@props(['href' => url('/'), 'size' => 'md'])

@php($tag = $href ? 'a' : 'span')
<{{ $tag }} @if($href) href="{{ $href }}" aria-label="COMPASS, in partnership with Project Dial-A-Friend, home" @endif {{ $attributes->class(['pdaf-lockup', 'pdaf-lockup--'.$size]) }}>
    <picture class="pdaf-lockup-seal">
        <source srcset="{{ asset('images/pdaf/pdaf-seal-160.webp') }}" type="image/webp">
        <img src="{{ asset('images/pdaf/pdaf-seal-160.png') }}" alt="Project Dial-A-Friend" width="48" height="48">
    </picture>
    <span class="pdaf-lockup-divider" aria-hidden="true"></span>
    <span class="pdaf-lockup-words">
        <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS" width="150" height="24">
        <small>Partnership with Project Dial-a-Friend</small>
    </span>
</{{ $tag }}>
