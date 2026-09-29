<?php

namespace Yurba\Cmf\Settings;

class Store
{
    protected static ?array $cache = null;

    protected static function path(): string
    {
        return storage_path('app/yurba-settings.json');
    }

    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $path = static::path();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        static::$cache = is_array($data) ? $data : [];

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
        static::setMany([$key => $value]);
    }

    // one locked read-modify-write, replaced atomically: readers never see a half-written file and saves can't drop each other's keys
    public static function setMany(array $values): void
    {
        $path = static::path();
        $lock = @fopen($path.'.lock', 'c');
        if ($lock) {
            flock($lock, LOCK_EX);
        }

        try {
            $all = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            if (! is_array($all)) {
                $all = static::$cache ?? [];
            }
            foreach ($values as $key => $value) {
                $all[$key] = $value;
            }

            $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
            file_put_contents($tmp, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (! @rename($tmp, $path)) {
                @unlink($tmp);
                file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
            static::$cache = $all;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
