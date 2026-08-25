<?php

namespace Yurba\Cmf\Pages;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yurba\Cmf\Media\Media;
use Yurba\Cmf\Settings\Store;

// "Image usage & thumbnails": where each media image is used and which preset
// thumbnails exist, with per-row/bulk/global generate and delete. Hidden from the nav.
class MediaUsagePage extends Page
{
    public function label(): string
    {
        return __('Image usage & thumbnails');
    }

    public function uriKey(): string
    {
        return 'media-usage';
    }

    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">image_search</span>';
    }

    public function inNav(): bool
    {
        return false;
    }

    // generate or delete thumbnails / images: single, selected, all, or unused.
    public function handle(Request $request): mixed
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $action = (string) $request->input('action');
        $id = $request->input('id');

        if ($id) {
            $ids = [$id];
        } elseif (in_array($action, ['generate_all', 'delete_thumbs_all'], true)) {
            $ids = $this->imageIds();
        } elseif ($action === 'delete_unused') {
            $ids = $this->unusedImageIds();
        } else {
            $ids = array_values(array_filter((array) $request->input('ids', [])));
        }

        $op = match ($action) {
            'generate', 'generate_all' => 'generate',
            'delete_thumbs', 'delete_thumbs_all' => 'delete_thumbs',
            'delete_media', 'delete_unused' => 'delete',
            default => null,
        };

        $done = 0;
        if ($op && $ids) {
            $widths = array_values($this->presetWidths());
            foreach (Media::query()->whereIn('id', $ids)->get() as $m) {
                if (! $m->isImage()) {
                    continue;
                }
                if ($op === 'generate') {
                    foreach ($widths as $w) {
                        Media::thumb($m->url, $w); // generates + caches when missing
                    }
                } elseif ($op === 'delete_thumbs') {
                    $this->deleteThumbs($m);
                } else {
                    $this->deleteThumbs($m);
                    Storage::disk($m->disk)->delete($m->path);
                    $m->delete();
                }
                $done++;
            }
        }

        return back()->with('yurba_status', $this->statusFor($op, $done));
    }

    public function render(Request $request): mixed
    {
        $usage = $this->usageIndex();
        $presets = $this->presetWidths();
        $filter = (string) $request->query('filter', '');

        $images = Media::query()->where('mime', 'like', 'image/%')->orderByDesc('id')->get();

        $rows = [];
        $missingCount = $unusedCount = 0;
        foreach ($images as $m) {
            $rel = $this->rel($m->path);
            $uses = $usage[$rel] ?? [];

            $thumbs = [];
            $missing = false;
            foreach ($presets as $name => $w) {
                $exists = $this->thumbExists($m, $w);
                $thumbs[$name] = ['width' => $w, 'exists' => $exists];
                $missing = $missing || ! $exists;
            }

            if ($missing) {
                $missingCount++;
            }
            if (! $uses) {
                $unusedCount++;
            }

            $rows[] = ['media' => $m, 'uses' => $uses, 'thumbs' => $thumbs, 'missing' => $missing];
        }

        if ($filter === 'missing') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['missing']));
        } elseif ($filter === 'unused') {
            $rows = array_values(array_filter($rows, fn ($r) => ! $r['uses']));
        }

        $perPage = 30;
        $current = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($current - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $current,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return view('yurba::media-usage', [
            'page' => $this,
            'rows' => $paginator,
            'presets' => $presets,
            'filter' => $filter,
            'counts' => [
                'all' => $images->count(),
                'missing' => $missingCount,
                'unused' => $unusedCount,
            ],
        ]);
    }

    // preset name => width, from config
    protected function presetWidths(): array
    {
        $presets = (array) config('yurba.media.thumb_presets', ['small' => 320, 'medium' => 640, 'large' => 1280]);

        return array_map('intval', $presets);
    }

    // path relative to the media dir: media/2026/08/x.jpg -> 2026/08/x.jpg
    protected function rel(string $path): string
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');

        return Str::after($path, $dir.'/');
    }

    protected function thumbExists(Media $m, int $width): bool
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $thumbPath = $dir.'-thumbs/'.$width.'/'.$this->rel($m->path);

        return Storage::disk($m->disk)->exists($thumbPath);
    }

    // remove all cached derivatives for an image, keeping the original.
    protected function deleteThumbs(Media $m): void
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        if ($dir === '' || ! str_starts_with($m->path, $dir.'/')) {
            return;
        }

        $rel = $this->rel($m->path);
        $storage = Storage::disk($m->disk);
        foreach ($storage->directories($dir.'-thumbs') as $widthDir) {
            $storage->delete($widthDir.'/'.$rel);
        }
        if ($m->thumb_path) {
            $storage->delete($m->thumb_path);
        }
    }

    /** @return array<int> ids of all image records */
    protected function imageIds(): array
    {
        return Media::query()->where('mime', 'like', 'image/%')->pluck('id')->all();
    }

    /** @return array<int> ids of image records referenced nowhere */
    protected function unusedImageIds(): array
    {
        $usage = $this->usageIndex();
        $ids = [];
        foreach (Media::query()->where('mime', 'like', 'image/%')->get() as $m) {
            if (! isset($usage[$this->rel($m->path)])) {
                $ids[] = $m->id;
            }
        }

        return $ids;
    }

    protected function statusFor(?string $op, int $done): string
    {
        if (! $done) {
            return __('Nothing to do.');
        }

        return match ($op) {
            'generate' => __('Generated thumbnails for :count image(s).', ['count' => $done]),
            'delete_thumbs' => __('Deleted thumbnails for :count image(s).', ['count' => $done]),
            'delete' => __('Deleted :count image(s).', ['count' => $done]),
            default => __('Done.'),
        };
    }

    // reverse index: media rel-path => uses. Scans every string column of every
    // resource record, the content store and settings, so an image counts as used
    // wherever its URL appears (covers and rich-text/body embeds alike).
    protected function usageIndex(): array
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $index = [];

        foreach (app('yurba.cmf')->resources() as $res) {
            try {
                $records = $res->query()->get();
            } catch (\Throwable $e) {
                continue;
            }

            foreach ($records as $rec) {
                $rels = [];
                foreach ($rec->getAttributes() as $val) {
                    if (is_string($val) && $val !== '') {
                        foreach ($this->extractRels($val, $dir) as $r) {
                            $rels[$r] = true;
                        }
                    }
                }
                if (! $rels) {
                    continue;
                }
                $entry = [
                    'resource' => $res->label(),
                    'title' => $res->title($rec),
                    'url' => route('yurba.resource.edit', [$res->uriKey(), $rec->getKey()]),
                ];
                foreach (array_keys($rels) as $r) {
                    $index[$r][] = $entry;
                }
            }
        }

        // content pages (home, contacts, …)
        try {
            if (Schema::hasTable('yurba_content')) {
                foreach (DB::table('yurba_content')->get(['key', 'data']) as $row) {
                    foreach ($this->extractRels((string) $row->data, $dir) as $r) {
                        $index[$r][] = ['resource' => 'Content', 'title' => $row->key, 'url' => null];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // panel settings (e.g. the logo)
        try {
            foreach (Store::all() as $key => $val) {
                if (is_string($val)) {
                    foreach ($this->extractRels($val, $dir) as $r) {
                        $index[$r][] = ['resource' => 'Settings', 'title' => (string) $key, 'url' => null];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // dedupe identical entries per image
        foreach ($index as $r => $uses) {
            $seen = [];
            $index[$r] = array_values(array_filter($uses, function ($u) use (&$seen) {
                $k = ($u['url'] ?? '').'|'.$u['resource'].'|'.$u['title'];
                if (isset($seen[$k])) {
                    return false;
                }

                return $seen[$k] = true;
            }));
        }

        return $index;
    }

    // extract media rel-paths (e.g. "2026/08/x.jpg") referenced anywhere in a string.
    // Matches /{dir}/… but not the /{dir}-thumbs/ derivative paths.
    protected function extractRels(string $text, string $dir): array
    {
        // unescape JSON-escaped slashes (\/media\/…) so content/repeater blobs match
        if (str_contains($text, '\\/')) {
            $text = str_replace('\\/', '/', $text);
        }

        if (! str_contains($text, '/'.$dir.'/')) {
            return [];
        }

        preg_match_all('#/'.preg_quote($dir, '#').'/([A-Za-z0-9/_.\-]+\.[A-Za-z0-9]+)#', $text, $m);

        return array_values(array_unique($m[1] ?? []));
    }
}
