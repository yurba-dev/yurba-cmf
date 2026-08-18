<?php

namespace Yurba\Cmf\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        return $this->thumb_path
            ? $this->relativize(Storage::disk($this->disk)->url($this->thumb_path))
            : $this->url;
    }

    // thumbnail url for a stored media url, by the same {dir}-thumbs convention
    // the media controller writes; unchanged when thumbnails are off or the url
    // is not a library file (e.g. a plain Image-field upload under /uploads)
    public static function thumbFor(string $url): string
    {
        $on = config('yurba.media.optimize', true) && config('yurba.media.thumbnails', true);
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');

        if (! $on || $dir === '' || ! str_contains($url, '/'.$dir.'/')) {
            return $url;
        }

        return str_replace('/'.$dir.'/', '/'.$dir.'-thumbs/', $url);
    }

    // return a site-root-relative URL (/storage/…) so stored values are domain-
    // independent; opt out for external CDN/S3 disks with yurba.media.relative_urls=false
    protected function relativize(string $url): string
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
