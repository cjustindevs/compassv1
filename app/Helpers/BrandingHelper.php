<?php

namespace App\Helpers;

/**
 * Central helper for COMPASS branding values so that logo paths, names, and
 * taglines are read from one source instead of being duplicated in views.
 */
class BrandingHelper
{
    /**
     * The application's display (full) name.
     */
    public static function name(): string
    {
        return config('app.brand.full_name', config('app.name', 'COMPASS'));
    }

    /**
     * Short name used in compact UI surfaces and PWA metadata.
     */
    public static function shortName(): string
    {
        return config('app.brand.short_name', static::name());
    }

    /**
     * The umbrella project this application belongs to.
     */
    public static function project(): string
    {
        return config('app.brand.project', '');
    }

    /**
     * The institution the platform serves.
     */
    public static function institution(): string
    {
        return config('app.brand.institution', '');
    }

    /**
     * Short brand tagline.
     */
    public static function tagline(): string
    {
        return config('app.brand.tagline', '');
    }

    /**
     * Theme color used in PWA and UI chrome.
     */
    public static function themeColor(): string
    {
        return config('app.brand.theme_color', '#04A052');
    }

    /**
     * Public URL for a given branded asset key.
     */
    public static function asset(string $key): string
    {
        return config("app.brand.assets.$key", '');
    }

    /**
     * Horizontal wordmark for light backgrounds.
     */
    public static function logoLight(): string
    {
        return static::asset('logo_light');
    }

    /**
     * Horizontal wordmark for dark backgrounds.
     */
    public static function logoDark(): string
    {
        return static::asset('logo_dark');
    }

    /**
     * Square emblem / icon.
     */
    public static function logoIcon(): string
    {
        return static::asset('logo_icon');
    }

    /**
     * Full page title, e.g. "Dashboard - COMPASS".
     */
    public static function title(string $page = ''): string
    {
        $brand = static::name();
        return $page === '' ? $brand : "$page - $brand";
    }
}
