<?php

namespace Yurba\Cmf\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property ?string $mime
 * @property int $size
 * @property ?int $width
 * @property ?int $height
 */
class Media extends Model
{
    protected $table = 'media';

    protected static array $thumbMemo = [];

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        return $this->relativize(Storage::disk($this->disk)->url($this->path));
    }

    public function getThumbUrlAttribute(): string
    {
        return static::thumb($this->url, config('yurba.media.thumb_default', 'small'));
    }

    // generated lazily; returns the original when no thumb can be derived, and '' for an empty value (a cleared optional image)
    public static function thumb(?string $url, int|string $size = 'small'): string
    {
        $url = (string) $url;
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');

        if ($url == '' || ! app('yurba.cmf')->mediaOptimize() || $dir == '' || ! str_contains($url, '/'.$dir.'/')) {
            return $url;
        }

        $width = static::thumbWidth($size);
        if ($width <= 0) {
            return $url;
        }

        $disk = (string) config('yurba.media.disk', 'public');
        $rel = Str::after($url, '/'.$dir.'/');
        // the url is a stored field value, so it must not steer reads and writes elsewhere on the disk
        if (str_contains($rel, '..')) {
            return $url;
        }
        $origPath = $dir.'/'.$rel;
        $thumbPath = $dir.'-thumbs/'.$width.'/'.$rel;
        $memo = $disk.':'.$thumbPath;

        if (isset(static::$thumbMemo[$memo])) {
            return static::$thumbMemo[$memo] ?: $url;
        }

        $storage = Storage::disk($disk);
        // remember "no thumbnail" (svg, pdf, oversized) instead of reading the whole file on every page view
        $skipKey = 'yurba.thumb.skip.'.md5($memo);

        try {
            if (! $storage->exists($thumbPath)) {
                if (static::cacheCall(fn () => Cache::get($skipKey)) || ! $storage->exists($origPath)) {
                    return static::$thumbMemo[$memo] = $url;
                }

                // concurrent first views of a page would all resize the same image
                $lock = static::cacheCall(fn () => Cache::lock('yurba.thumb.'.md5($memo), 60));
                if ($lock && ! static::cacheCall(fn () => $lock->get(), true)) {
                    return $url;
                }
                try {
                    if (! $storage->exists($thumbPath)) {
                        $t = ImageOptimizer::thumbnail($storage->get($origPath), $width);
                        if (! $t) {
                            static::cacheCall(fn () => Cache::put($skipKey, true, now()->addDay()));

                            return static::$thumbMemo[$memo] = $url;
                        }
                        $storage->put($thumbPath, $t[0]);
                    }
                } finally {
                    if ($lock) {
                        static::cacheCall(fn () => $lock->release());
                    }
                }
            }

            return static::$thumbMemo[$memo] = static::relativizeUrl($storage->url($thumbPath));
        } catch (\Throwable $e) {
            return $url;
        }
    }

    // the cache is an optimization here; a store without locks must not break thumbs
    protected static function cacheCall(callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public static function forgetThumbSkip(string $path, ?string $disk = null): void
    {
        $disk ??= (string) config('yurba.media.disk', 'public');
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $rel = Str::after($path, $dir.'/');

        foreach (array_unique(array_merge((array) config('yurba.media.thumb_presets', []), [320, 640, 1280])) as $width) {
            $memo = $disk.':'.$dir.'-thumbs/'.(int) $width.'/'.$rel;
            unset(static::$thumbMemo[$memo]);
            static::cacheCall(fn () => Cache::forget('yurba.thumb.skip.'.md5($memo)));
        }
    }

    // 0 = unknown size or thumbnails disabled
    public static function thumbWidth(int|string $size): int
    {
        if (is_int($size)) {
            return max(0, $size);
        }

        $presets = (array) config('yurba.media.thumb_presets', ['small' => 320, 'medium' => 640, 'large' => 1280]);

        return (int) ($presets[$size] ?? 0);
    }

    // back-compat: maps to the default preset
    public static function thumbFor(?string $url): string
    {
        return static::thumb($url, config('yurba.media.thumb_default', 'small'));
    }

    // site-root-relative so stored values survive a domain change; external CDN/S3 disks opt out via yurba.media.relative_urls
    protected function relativize(string $url): string
    {
        return static::relativizeUrl($url);
    }

    protected static function relativizeUrl(string $url): string
    {
        if (! config('yurba.media.relative_urls', true)) {
            return $url;
        }

        // collapse an accidental leading // (e.g. a trailing-slash APP_URL) that would read as a protocol-relative host
        return '/'.ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < count($units) - 1);

        return round($bytes, 1).' '.$units[$i];
    }
}
