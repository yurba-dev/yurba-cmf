<?php

namespace Yurba\Cmf\Media;

// dependency-free (GD) downscale/re-encode + thumbnails on raw bytes; strips
// metadata. non-images / unsupported types return null (store as-is).
class ImageOptimizer
{
    // skip anything larger to avoid decoding it into memory (oom)
    public const MAX_PIXELS = 40_000_000;

    protected const RASTER = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor');
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
        if (! $i || ! in_array($i[2], self::RASTER, true) || $i[0] * $i[1] > self::MAX_PIXELS) {
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
