<?php

namespace Yurba\Cmf\Settings;

// key/value settings store backed by a json file under storage/app (no db/migration)
class Store
{
    protected static ?array $cache = null;

    protected static function path(): string
    {
        return storage_path('app/yurba-settings.json');
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $path = static::path();
        static::$cache = is_file($path)
            ? (json_decode((string) file_get_contents($path), true) ?: [])
            : [];

        return static::$cache;
    }

    // empty string / null count as "unset" so callers get their fallback
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::all()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $all = static::all();
        $all[$key] = $value;
        static::$cache = $all;

        file_put_contents(
            static::path(),
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
