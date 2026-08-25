<?php

namespace Yurba\Cmf\Media;

// dependency-free (GD) downscale/re-encode + thumbnails on raw bytes; strips
// metadata. non-images / unsupported types return null (store as-is).
class ImageOptimizer
{
    // absolute ceiling; the effective limit scales with memory_limit (see maxPixels()).
    public const MAX_PIXELS = 200_000_000;

    protected const RASTER = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor');
    }

    // GD decodes the whole bitmap (~4 B/px) before resizing, so scale the limit to free memory.
    public static function maxPixels(): int
    {
        $limit = self::memoryLimitBytes();
        if ($limit <= 0) {
            return self::MAX_PIXELS; // unlimited memory_limit: use the hard ceiling
        }

        $free = $limit - memory_get_usage(true);
        $budgetPixels = (int) ($free * 0.5 / 4);

        return max(4_000_000, min(self::MAX_PIXELS, $budgetPixels));
    }

    // parse ini memory_limit into bytes; -1 = unlimited
    protected static function memoryLimitBytes(): int
    {
        $v = trim((string) ini_get('memory_limit'));
        if ($v === '' || $v === '-1') {
            return -1;
        }

        $num = (int) $v;

        return match (strtolower((string) substr($v, -1))) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $v,
        };
    }

    // describe an image for logging/diagnostics without modifying it
    /** @return array{raster: bool, width: ?int, height: ?int, pixels: int, max_pixels: int, over_limit: bool} */
    public static function inspect(string $data): array
    {
        $i = @getimagesizefromstring($data);
        $raster = $i && in_array($i[2], self::RASTER, true);
        $w = $raster ? (int) $i[0] : null;
        $h = $raster ? (int) $i[1] : null;
        $px = ($w && $h) ? $w * $h : 0;
        $max = self::maxPixels();

        return [
            'raster' => (bool) $raster,
            'width' => $w,
            'height' => $h,
            'pixels' => $px,
            'max_pixels' => $max,
            'over_limit' => $raster && $px > $max,
        ];
    }

    /** @return array{0: string, 1: int, 2: int}|null [bytes, width, height] */
    public static function process(string $data, int $maxWidth, int $quality): ?array
    {
        $info = self::info($data);
        if (! $info) {
            return null;
        }
        [$w, $h, $type] = $info;

        $img = @imagecreatefromstring($data);
        if (! $img) {
            return null;
        }

        if ($maxWidth > 0 && $w > $maxWidth) {
            $nh = (int) max(1, round($h * ($maxWidth / $w)));
            $img = self::resample($img, $type, $w, $h, $maxWidth, $nh);
            $w = $maxWidth;
            $h = $nh;
        }

        $bytes = self::encode($img, $type, $quality);
        imagedestroy($img);

        return $bytes === null ? null : [$bytes, $w, $h];
    }

    // never upscales
    /** @return array{0: string, 1: int, 2: int}|null [bytes, width, height] */
    public static function thumbnail(string $data, int $width): ?array
    {
        $info = self::info($data);
        if (! $info) {
            return null;
        }
        [$w, $h, $type] = $info;

        $img = @imagecreatefromstring($data);
        if (! $img) {
            return null;
        }

        $nw = min($width, $w);
        $nh = (int) max(1, round($h * ($nw / $w)));
        $thumb = self::resample($img, $type, $w, $h, $nw, $nh);
        imagedestroy($img);

        $bytes = self::encode($thumb, $type, 80);
        imagedestroy($thumb);

        return $bytes === null ? null : [$bytes, $nw, $nh];
    }

    /** @return array{0: int, 1: int, 2: int}|null [width, height, IMAGETYPE_*] */
    protected static function info(string $data): ?array
    {
        if (! self::available()) {
            return null;
        }
        $i = @getimagesizefromstring($data);
        if (! $i || ! in_array($i[2], self::RASTER, true) || $i[0] * $i[1] > self::maxPixels()) {
            return null;
        }

        return [$i[0], $i[1], $i[2]];
    }

    protected static function resample($src, int $type, int $sw, int $sh, int $dw, int $dh)
    {
        $dst = imagecreatetruecolor($dw, $dh);
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dw, $dh, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);

        return $dst;
    }

    protected static function encode($img, int $type, int $quality): ?string
    {
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($img, null, $quality),
            IMAGETYPE_PNG => imagepng($img, null, 9),
            IMAGETYPE_GIF => imagegif($img),
            IMAGETYPE_WEBP => imagewebp($img, null, $quality),
            default => false,
        };
        $out = ob_get_clean();

        return $ok ? $out : null;
    }
}
