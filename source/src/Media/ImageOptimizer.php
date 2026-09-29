<?php

namespace Yurba\Cmf\Media;

class ImageOptimizer
{
    // absolute ceiling; the effective limit scales with memory_limit
    public const MAX_PIXELS = 200_000_000;

    protected const RASTER = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public static function available(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor');
    }

    // GD decodes the whole bitmap (~4 B/px) before resizing, so scale the limit to free memory
    public static function maxPixels(): int
    {
        $limit = self::memoryLimitBytes();
        if ($limit <= 0) {
            return self::MAX_PIXELS;
        }

        $free = $limit - memory_get_usage(true);
        $budgetPixels = (int) ($free * 0.5 / 4);

        return max(4_000_000, min(self::MAX_PIXELS, $budgetPixels));
    }

    // -1 = unlimited
    protected static function memoryLimitBytes(): int
    {
        $v = trim((string) ini_get('memory_limit'));
        if ($v == '' || $v == '-1') {
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

        // GD keeps only the first frame of an animation
        if (self::isAnimated($data, $type)) {
            return null;
        }

        $img = @imagecreatefromstring($data);
        if (! $img) {
            return null;
        }

        // the re-encode drops EXIF, so its rotation has to be baked into the pixels
        [$img, $w, $h, $rotated] = self::orient($img, $data, $type, $w, $h);

        $resized = false;
        if ($maxWidth > 0 && $w > $maxWidth) {
            $nh = (int) max(1, round($h * ($maxWidth / $w)));
            $img = self::resample($img, $type, $w, $h, $maxWidth, $nh);
            $w = $maxWidth;
            $h = $nh;
            $resized = true;
        }

        $bytes = self::encode($img, $type, $quality);
        imagedestroy($img);
        if ($bytes === null) {
            return null;
        }

        // a re-encode that came out larger is not worth it, unless it is what strips location/camera metadata
        if (! $resized && ! $rotated && strlen($bytes) >= strlen($data) && ! self::hasMetadata($data, $type)) {
            return null;
        }

        return [$bytes, $w, $h];
    }

    public static function isAnimated(string $data, ?int $type = null): bool
    {
        $type ??= (@getimagesizefromstring($data)[2] ?? null);

        return match ($type) {
            IMAGETYPE_GIF => preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $data) > 1,
            IMAGETYPE_WEBP => substr($data, 12, 4) == 'VP8X' && (ord($data[20] ?? "\0") & 0x02) == 0x02,
            IMAGETYPE_PNG => ($actl = strpos($data, 'acTL')) !== false && $actl < (int) strpos($data, 'IDAT'),
            default => false,
        };
    }

    protected static function hasMetadata(string $data, int $type): bool
    {
        $head = substr($data, 0, 262144);

        return match ($type) {
            IMAGETYPE_JPEG => str_contains($head, "Exif\0\0") || str_contains($head, 'http://ns.adobe.com/xap/'),
            IMAGETYPE_PNG => (bool) preg_match('/eXIf|tEXt|iTXt|zTXt/', $head),
            IMAGETYPE_WEBP => str_contains($head, 'EXIF') || str_contains($head, 'XMP '),
            default => false,
        };
    }

    /** @return array{0: mixed, 1: int, 2: int, 3: bool} [image, width, height, changed] */
    protected static function orient($img, string $data, int $type, int $w, int $h): array
    {
        if ($type != IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return [$img, $w, $h, false];
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $data);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $o = (int) ($exif['Orientation'] ?? 1);
        if ($o < 2 || $o > 8) {
            return [$img, $w, $h, false];
        }

        if (in_array($o, [2, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        } elseif (in_array($o, [4, 5], true)) {
            imageflip($img, IMG_FLIP_VERTICAL);
        }

        $angle = match ($o) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle != 0) {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated) {
                imagedestroy($img);
                $img = $rotated;
            }
        }

        return [$img, imagesx($img), imagesy($img), true];
    }

    /** @return array{0: string, 1: int, 2: int}|null [bytes, width, height] */
    public static function thumbnail(string $data, int $width): ?array
    {
        $info = self::info($data);
        if (! $info) {
            return null;
        }
        [$w, $h, $type] = $info;

        if (self::isAnimated($data, $type)) {
            return null;
        }

        $img = @imagecreatefromstring($data);
        if (! $img) {
            return null;
        }

        [$img, $w, $h] = self::orient($img, $data, $type, $w, $h);

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
