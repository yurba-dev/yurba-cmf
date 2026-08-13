<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yurba\Cmf\Media\ImageOptimizer;
use Yurba\Cmf\Media\Media;

class MediaController extends Controller
{
    protected function guard(): void
    {
        abort_unless((bool) config('yurba.media.enabled', true), 404);
    }

    public function index(Request $request)
    {
        $this->guard();

        $search = trim((string) $request->query('q', ''));
        $media = Media::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate((int) config('yurba.media.per_page', 24))
            ->withQueryString();

        return view('yurba::media', compact('media', 'search'));
    }

    // json feed for the media picker modal
    public function list(Request $request)
    {
        $this->guard();

        $search = trim((string) $request->query('q', ''));
        $perPage = (int) config('yurba.media.picker_per_page', 36);
        $paginator = Media::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'items' => $paginator->getCollection()->map(fn (Media $m) => $this->present($m))->values(),
            'has_more' => $paginator->hasMorePages(),
            'page' => $paginator->currentPage(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function present(Media $m): array
    {
        return ['id' => $m->id, 'name' => $m->name, 'url' => $m->url, 'thumb' => $m->thumb_url, 'is_image' => $m->isImage()];
    }

    public function store(Request $request)
    {
        $this->guard();

        $mimes = (string) config('yurba.media.mimes', 'jpeg,jpg,png,gif,webp,avif,pdf');
        $maxKb = (int) config('yurba.media.max_kb', 8192);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKb],
        ]);

        $disk = (string) config('yurba.media.disk', 'public');
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $optimize = app('yurba.cmf')->mediaOptimize();
        $maxWidth = (int) config('yurba.media.max_width', 2560);
        $quality = (int) config('yurba.media.quality', 82);
        $thumbs = $optimize && (bool) config('yurba.media.thumbnails', true);
        $thumbWidth = (int) config('yurba.media.thumb_width', 480);
        $created = [];

        foreach ((array) $request->file('files', []) as $file) {
            $data = (string) file_get_contents($file->getRealPath());
            $mime = (string) $file->getClientMimeType();
            $isImage = str_starts_with($mime, 'image/');
            $width = $height = null;

            if ($isImage && $optimize && ($opt = ImageOptimizer::process($data, $maxWidth, $quality))) {
                [$data, $width, $height] = $opt;
            } elseif ($isImage && ($info = @getimagesizefromstring($data))) {
                [$width, $height] = $info;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension() ?: 'bin');
            $path = $dir.'/'.date('Y/m').'/'.Str::random(40).'.'.$ext;
            Storage::disk($disk)->put($path, $data);

            $thumbPath = null;
            if ($isImage && $thumbs && ($t = ImageOptimizer::thumbnail($data, $thumbWidth))) {
                $thumbPath = $dir.'-thumbs/'.Str::after($path, $dir.'/');
                Storage::disk($disk)->put($thumbPath, $t[0]);
            }

            $created[] = Media::create([
                'disk' => $disk,
                'path' => $path,
                'thumb_path' => $thumbPath,
                'name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => strlen($data),
                'width' => $width,
                'height' => $height,
            ]);
        }

        // the picker uploads via fetch and wants the created records back
        if ($request->expectsJson()) {
            return response()->json(['items' => array_map(fn (Media $m) => $this->present($m), $created)]);
        }

        $count = count($created);

        return back()->with('yurba_status', $count.' file'.($count === 1 ? '' : 's').' uploaded.');
    }

    public function destroy(int|string $id)
    {
        $this->guard();

        $media = Media::findOrFail($id);
        Storage::disk($media->disk)->delete($media->path);
        if ($media->thumb_path) {
            Storage::disk($media->disk)->delete($media->thumb_path);
        }
        $media->delete();

        return back()->with('yurba_status', 'Media deleted.');
    }
}
