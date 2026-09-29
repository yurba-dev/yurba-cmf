<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yurba\Cmf\Media\ImageOptimizer;
use Yurba\Cmf\Media\Media;
use Yurba\Cmf\Media\MediaOptimization;
use Yurba\Cmf\Support\Upload;

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
            ->when($search != '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->latest('id')
            ->paginate((int) config('yurba.media.per_page', 24))
            ->withQueryString();

        return view('yurba::media', compact('media', 'search'));
    }

    public function list(Request $request)
    {
        $this->guard();

        $search = trim((string) $request->query('q', ''));
        $perPage = (int) config('yurba.media.picker_per_page', 36);
        $paginator = Media::query()
            ->when($search != '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'items' => $paginator->getCollection()->map(fn (Media $m) => $this->present($m))->values(),
            'has_more' => $paginator->hasMorePages(),
            'page' => $paginator->currentPage(),
        ]);
    }

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
        $allowed = array_values(array_filter(array_map(fn ($m) => strtolower(trim($m)), explode(',', $mimes))));

        $disk = (string) config('yurba.media.disk', 'public');
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $optimize = app('yurba.cmf')->mediaOptimize();
        $maxWidth = (int) config('yurba.media.max_width', 2560);
        $quality = (int) config('yurba.media.quality', 82);
        $created = [];

        foreach ((array) $request->file('files', []) as $file) {
            $data = $origData = (string) file_get_contents($file->getRealPath());
            $origSize = strlen($origData);
            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
            $isImage = str_starts_with($mime, 'image/');
            $width = $height = null;
            $optimized = false;

            if ($isImage && $optimize && ($opt = ImageOptimizer::process($data, $maxWidth, $quality))) {
                [$data, $width, $height] = $opt;
                $optimized = true;
            } elseif ($isImage && ($info = @getimagesizefromstring($data))) {
                [$width, $height] = $info;
            }

            // named by the sniffed type: a client ".html" would be served as a page on this origin
            $ext = Upload::safeExtension($file, $allowed) ?? 'bin';
            $path = $dir.'/'.date('Y/m').'/'.Str::random(40).'.'.$ext;
            Storage::disk($disk)->put($path, $data);

            $created[] = Media::create([
                'disk' => $disk,
                'path' => $path,
                'thumb_path' => null,
                'name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => strlen($data),
                'width' => $width,
                'height' => $height,
            ]);

            if ($isImage) {
                [$status, $reason] = MediaOptimization::classify($origData, $optimize, $optimized, false);
                MediaOptimization::record([
                    'path' => $path,
                    'source' => 'upload',
                    'status' => $status,
                    'reason' => $reason,
                    'optimized' => $optimized,
                    'thumbnailed' => false,
                    'width' => $width,
                    'height' => $height,
                    'orig_size' => $origSize,
                    'new_size' => strlen($data),
                ]);
            }
        }

        // the picker uploads via fetch and wants the created records back
        if ($request->expectsJson()) {
            return response()->json(['items' => array_map(fn (Media $m) => $this->present($m), $created)]);
        }

        $count = count($created);

        return back()->with('yurba_status', __(':count file(s) uploaded.', ['count' => $count]));
    }

    public function destroy(int|string $id)
    {
        $this->guard();

        $media = Media::findOrFail($id);
        $storage = Storage::disk($media->disk);
        $storage->delete($media->path);

        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        if ($dir != '' && str_starts_with($media->path, $dir.'/')) {
            $rel = Str::after($media->path, $dir.'/');
            foreach ($storage->directories($dir.'-thumbs') as $widthDir) {
                $storage->delete($widthDir.'/'.$rel);
            }
        }
        if ($media->thumb_path) {
            $storage->delete($media->thumb_path); // legacy single-thumb cleanup
        }
        $media->delete();

        return back()->with('yurba_status', __('Media deleted.'));
    }
}
