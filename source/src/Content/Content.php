<?php

namespace Yurba\Cmf\Content;

use Illuminate\Support\Facades\Schema;

// read/write content-page values on the frontend and from ContentPage screens
class Content
{
    protected static array $cache = [];

    /** @return array<string, mixed> a page's field values, [field => value] */
    public static function get(string $key): array
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        // safe before the table exists (e.g. during migrations)
        if (! Schema::hasTable('yurba_content')) {
            return static::$cache[$key] = [];
        }

        return static::$cache[$key] = ContentRecord::where('key', $key)->first()?->data ?? [];
    }

    public static function field(string $key, string $name, mixed $default = null): mixed
    {
        return static::get($key)[$name] ?? $default;
    }

    public static function put(string $key, array $data): void
    {
        ContentRecord::updateOrCreate(['key' => $key], ['data' => $data]);
        static::$cache[$key] = $data;
    }
}
