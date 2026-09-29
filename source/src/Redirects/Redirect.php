<?php

namespace Yurba\Cmf\Redirects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $from
 * @property string $to
 * @property int    $status
 * @property bool   $enabled
 * @property int    $hits
 */
class Redirect extends Model
{
    protected $table = 'yurba_redirects';

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'enabled' => 'boolean',
        'hits' => 'integer',
    ];

    public const CACHE_KEY = 'yurba.redirects.map';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function normalize(?string $path): string
    {
        $path = parse_url((string) $path, PHP_URL_PATH);

        return trim(rawurldecode((string) $path), '/');
    }

    public static function internalPath(string $to, string $host): ?string
    {
        $to = trim($to);
        if (preg_match('#^(https?:)?//#i', $to)) {
            $targetHost = strtolower((string) parse_url(str_starts_with($to, '//') ? 'http:'.$to : $to, PHP_URL_HOST));
            if ($targetHost == '' || $targetHost != strtolower($host)) {
                return null;
            }
        }

        return static::normalize($to);
    }

    public static function loops(string $from, array $map, string $host): bool
    {
        $seen = [$from => true];
        $current = $from;

        for ($i = 0; $i < 32 && isset($map[$current]); $i++) {
            $next = static::internalPath((string) $map[$current]['to'], $host);
            if ($next === null) {
                return false;
            }
            if (isset($seen[$next])) {
                return true;
            }
            $seen[$next] = true;
            $current = $next;
        }

        return isset($map[$current]);
    }

    public static function wouldLoop(string $from, string $to, string $host, mixed $ignoreId = null): bool
    {
        $map = static::query()
            ->where('enabled', true)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['from', 'to'])
            ->keyBy(fn ($r) => static::normalize($r->from))
            ->map(fn ($r) => ['to' => $r->to])
            ->all();

        $from = static::normalize($from);
        $map[$from] = ['to' => $to];

        return static::loops($from, $map, $host);
    }

    // normalized from => [to, status, id]
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()
                ->where('enabled', true)
                ->get(['id', 'from', 'to', 'status'])
                ->keyBy(fn ($r) => static::normalize($r->from))
                ->map(fn ($r) => ['to' => $r->to, 'status' => $r->status ?: 301, 'id' => $r->id])
                ->all();
        });
    }
}
