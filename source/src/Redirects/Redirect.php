<?php

namespace Yurba\Cmf\Redirects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * a url redirect looked up via a forever-cached map, flushed on save/delete.
 *
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

    // drop scheme/host/query and surrounding slashes so "/Old/Page/" and
    // "Old/Page?x=1" reduce to the same key
    public static function normalize(?string $path): string
    {
        $path = parse_url((string) $path, PHP_URL_PATH);

        return trim(rawurldecode((string) $path), '/');
    }

    /** @return array<string, array{to: string, status: int, id: int}> */
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
