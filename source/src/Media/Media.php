<?php

namespace Yurba\Cmf\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * a file in the media library, stored on a configurable Storage disk.
 *
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

    // small derivative for grids/pickers; falls back to the full image
    public function getThumbUrlAttribute(): string
    {
        return static::thumb($this->url, config('yurba.media.thumb_default', 'small'));
    }

    // thumbnail URL at the given size (preset name or pixel width), generated lazily on
    // first request and cached under {dir}-thumbs/{width}/. Returns the original when it
    // can't derive one (not a library file, optimization off, unknown size, generation fails).
    public static function thumb(string $url, int|string $size = 'small'): string
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');

        if (! config('yurba.media.optimize', true) || $dir === '' || ! str_contains($url, '/'.$dir.'/')) {
            return $url;
        }

        $width = static::thumbWidth($size);
        if ($width <= 0) {
            return $url;
        }

        $disk = (string) config('yurba.media.disk', 'public');
        $rel = Str::after($url, '/'.$dir.'/');            // 2026/08/x.jpeg
        $origPath = $dir.'/'.$rel;
        $thumbPath = $dir.'-thumbs/'.$width.'/'.$rel;     // media-thumbs/320/2026/08/x.jpeg
        $storage = Storage::disk($disk);

        try {
            if (! $storage->exists($thumbPath)) {
                if (! $storage->exists($origPath)) {
                    return $url;
                }
                $t = ImageOptimizer::thumbnail($storage->get($origPath), $width);
                if (! $t) {
                    return $url; // non-raster / oversized — serve the original
                }
                $storage->put($thumbPath, $t[0]);
            }

            return static::relativizeUrl($storage->url($thumbPath));
        } catch (\Throwable $e) {
            return $url;
        }
    }

    // resolve a preset name or raw width to a pixel width (0 = unknown/disabled)
    public static function thumbWidth(int|string $size): int
    {
        if (is_int($size)) {
            return max(0, $size);
        }

        $presets = (array) config('yurba.media.thumb_presets', ['small' => 320, 'medium' => 640, 'large' => 1280]);

        return (int) ($presets[$size] ?? 0);
    }

    // back-compat: maps to the default preset.
    public static function thumbFor(string $url): string
    {
        return static::thumb($url, config('yurba.media.thumb_default', 'small'));
    }

    // return a site-root-relative URL (/storage/…) so stored values are domain-
    // independent; opt out for external CDN/S3 disks with yurba.media.relative_urls=false
    protected function relativize(string $url): string
    {
        return static::relativizeUrl($url);
    }

    protected static function relativizeUrl(string $url): string
    {
        if (! config('yurba.media.relative_urls', true)) {
            return $url;
        }

        // site-root-relative, collapsing any accidental leading // (e.g. a
        // trailing-slash APP_URL) that would otherwise read as a protocol-relative host
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
