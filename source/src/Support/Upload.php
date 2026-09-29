<?php

namespace Yurba\Cmf\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// files land in the web root, so they are checked by content and named by the detected type, never the client's extension (shell.php, gif/html polyglots)
class Upload
{
    public static function imageExtensions(): array
    {
        $mimes = (string) config('yurba.uploads.mimes', 'jpeg,jpg,png,gif,webp,avif');

        return array_values(array_filter(array_map(fn ($m) => strtolower(trim($m)), explode(',', $mimes))));
    }

    public static function publicImage(UploadedFile $file, string $dir, string $attribute = 'file'): string
    {
        $allowed = static::imageExtensions();
        $maxKb = (int) config('yurba.uploads.image_max_kb', config('yurba.media.max_kb', 8192));

        $validator = Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimes:'.implode(',', $allowed), 'max:'.$maxKb],
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages([$attribute => $validator->errors()->all()]);
        }

        // laravel's "image" rule lets svg through and rejects avif, so check the sniffed type here
        $ext = static::safeExtension($file, $allowed);
        if ($ext === null || $ext == 'svg' || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw ValidationException::withMessages([$attribute => [__('validation.mimes', ['attribute' => $attribute, 'values' => implode(', ', $allowed)])]]);
        }

        $dir = trim($dir, '/');
        $target = public_path($dir);
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }
        $filename = date('Ymd').'-'.bin2hex(random_bytes(6)).'.'.$ext;
        $file->move($target, $filename);

        return '/'.$dir.'/'.$filename;
    }

    public static function safeExtension(UploadedFile $file, array $allowed): ?string
    {
        $ext = strtolower((string) $file->guessExtension());
        if ($ext == 'jpeg') {
            $ext = 'jpg';
        }
        if ($ext == '' || ! preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
            return null;
        }
        if (! in_array($ext, $allowed, true) && ! ($ext == 'jpg' && in_array('jpeg', $allowed, true))) {
            return null;
        }

        return $ext;
    }
}
