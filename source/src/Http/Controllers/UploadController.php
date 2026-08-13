<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// inline image uploads from the editor, moved under public/{dir}, returns { url }.
// svg is refused on purpose: it can carry scripts and become stored xss.
class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $mimes = (string) config('yurba.uploads.mimes', 'jpeg,jpg,png,gif,webp,avif');
        $maxKb = (int) config('yurba.uploads.max_kb', 4096);

        // the image rule uses getimagesize(), which also rejects svg
        $request->validate([
            'file' => ['required', 'image', 'mimes:'.$mimes, 'max:'.$maxKb],
        ]);

        $file = $request->file('file');
        $dir = trim((string) config('yurba.uploads.dir', 'uploads/editor'), '/');
        $target = public_path($dir);
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $name = date('Ymd').'-'.bin2hex(random_bytes(8)).'.'.$ext;
        $file->move($target, $name);

        return response()->json(['url' => '/'.$dir.'/'.$name]);
    }
}
