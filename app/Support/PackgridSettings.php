<?php

namespace App\Support;

use App\Enums\PackageFormat;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class PackgridSettings
{
    protected const CACHE_KEY = 'packgrid_settings';

    protected const CACHE_TTL = 3600; // 1 hour

    public static function composerEnabled(): bool
    {
        return static::get()->composer_enabled;
    }

    public static function npmEnabled(): bool
    {
        return static::get()->npm_enabled;
    }

    public static function dockerEnabled(): bool
    {
        return static::get()->docker_enabled;
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        return match ($feature) {
            'composer' => static::composerEnabled(),
            'npm' => static::npmEnabled(),
            'docker' => static::dockerEnabled(),
            default => false,
        };
    }

    /**
     * Get enabled package formats for Repository form/filter.
     *
     * @return array<string, string>
     */
    public static function getEnabledFormats(): array
    {
        $formats = [];

        if (static::composerEnabled()) {
            $formats[PackageFormat::Composer->value] = PackageFormat::Composer->label();
        }

        if (static::npmEnabled()) {
            $formats[PackageFormat::Npm->value] = PackageFormat::Npm->label();
        }

        return $formats;
    }

    /**
     * Get enabled package types for Documentation page.
     *
     * @return array<string>
     */
    public static function getEnabledPackageTypes(): array
    {
        $types = [];

        if (static::composerEnabled()) {
            $types[] = 'composer';
        }

        if (static::npmEnabled()) {
            $types[] = 'npm';
        }

        if (static::dockerEnabled()) {
            $types[] = 'docker';
        }

        return $types;
    }

    /**
     * Check if multiple package formats are enabled (for showing filter).
     */
    public static function hasMultipleFormats(): bool
    {
        return count(static::getEnabledFormats()) > 1;
    }

    /**
     * Check if repositories feature should be shown (composer or npm enabled).
     */
    public static function repositoriesEnabled(): bool
    {
        return static::composerEnabled() || static::npmEnabled();
    }

    /**
     * Get the first enabled format as default.
     */
    public static function getDefaultFormat(): ?string
    {
        $formats = static::getEnabledFormats();

        return array_key_first($formats);
    }

    /**
     * Get the first enabled package type as default.
     */
    public static function getDefaultPackageType(): ?string
    {
        $types = static::getEnabledPackageTypes();

        return $types[0] ?? null;
    }

    /**
     * Clear the cached settings.
     */
    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Get the settings model, cached for performance.
     */
    protected static function get(): Setting
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return Setting::firstOrCreate([], [
                'composer_enabled' => true,
                'npm_enabled' => true,
                'docker_enabled' => true,
            ]);
        });
    }
}
