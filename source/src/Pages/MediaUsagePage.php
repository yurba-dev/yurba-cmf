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

class MediaUsagePage extends Page
{
    protected static array $usageSources = [];

    protected bool $scanFailed = false;

    // for places the scan can't see: MediaUsagePage::usageSource('Orders', fn () => [title => text, ...])
    public static function usageSource(string $label, callable $source): void
    {
        static::$usageSources[$label] = $source;
    }

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
        } elseif ($action == 'delete_unused') {
            // only ids the admin reviewed on the "unused" list, and only if a fresh scan still finds them unused
            $reviewed = array_map('strval', (array) $request->input('ids', []));
            $ids = array_values(array_filter($this->unusedImageIds(), fn ($i) => in_array((string) $i, $reviewed, true)));
            if ($this->scanFailed) {
                return back()->with('yurba_status', __('Usage scan was incomplete, nothing was deleted.'));
            }
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
                if ($op == 'generate') {
                    Media::forgetThumbSkip($m->path, $m->disk);
                    foreach ($widths as $w) {
                        Media::thumb($m->url, $w);
                    }
                } elseif ($op == 'delete_thumbs') {
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
        $unusedIds = [];
        $missingCount = $unusedCount = 0;
        $existing = [];
        foreach ($images as $m) {
            $rel = $this->rel($m->path);
            $uses = $usage[$rel] ?? [];

            $thumbs = [];
            $missing = false;
            foreach ($presets as $name => $w) {
                $existing[$m->disk][$w] ??= $this->thumbIndex($m->disk, $w);
                $exists = isset($existing[$m->disk][$w][$rel]);
                $thumbs[$name] = ['width' => $w, 'exists' => $exists];
                $missing = $missing || ! $exists;
            }

            if ($missing) {
                $missingCount++;
            }
            if (! $uses) {
                $unusedCount++;
                $unusedIds[] = $m->id;
            }

            $rows[] = ['media' => $m, 'uses' => $uses, 'thumbs' => $thumbs, 'missing' => $missing];
        }

        if ($filter == 'missing') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['missing']));
        } elseif ($filter == 'unused') {
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
            'unusedIds' => $unusedIds,
            'scanFailed' => $this->scanFailed,
            'counts' => [
                'all' => $images->count(),
                'missing' => $missingCount,
                'unused' => $unusedCount,
            ],
        ]);
    }

    protected function presetWidths(): array
    {
        $presets = (array) config('yurba.media.thumb_presets', ['small' => 320, 'medium' => 640, 'large' => 1280]);

        return array_map('intval', $presets);
    }

    protected function rel(string $path): string
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');

        return Str::after($path, $dir.'/');
    }

    // one directory listing per preset instead of an exists() call per image
    protected function thumbIndex(string $disk, int $width): array
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $base = $dir.'-thumbs/'.$width.'/';

        try {
            $files = Storage::disk($disk)->allFiles($base);
        } catch (\Throwable $e) {
            return [];
        }

        $index = [];
        foreach ($files as $file) {
            $index[Str::after($file, $base)] = true;
        }

        return $index;
    }

    protected function thumbExists(Media $m, int $width): bool
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $thumbPath = $dir.'-thumbs/'.$width.'/'.$this->rel($m->path);

        return Storage::disk($m->disk)->exists($thumbPath);
    }

    protected function deleteThumbs(Media $m): void
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        if ($dir == '' || ! str_starts_with($m->path, $dir.'/')) {
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

    protected function imageIds(): array
    {
        return Media::query()->where('mime', 'like', 'image/%')->pluck('id')->all();
    }

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

    // an image counts as used wherever its URL appears: any string column of any resource record, the content store or settings
    protected function usageIndex(): array
    {
        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $index = [];
        $this->scanFailed = false;

        foreach (app('yurba.cmf')->resources() as $res) {
            try {
                // trashed rows can still be restored, so their images count as used
                $query = $res->query();
                if ($res->usesSoftDeletes()) {
                    $query->withTrashed();
                }

                foreach ($query->reorder()->lazyById(200) as $rec) {
                    $rels = [];
                    foreach ($rec->getAttributes() as $val) {
                        if (is_string($val) && $val != '') {
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
            } catch (\Throwable $e) {
                $this->scanFailed = true;

                continue;
            }
        }

        // per-locale values, seo og images and revision snapshots live in side tables
        $side = [
            'yurba_translations' => ['label' => 'Translation', 'column' => 'value', 'title' => fn ($row) => $row->translatable_type.' #'.$row->translatable_id.' ('.$row->locale.')'],
            'yurba_seo' => ['label' => 'SEO', 'column' => 'og_image', 'title' => fn ($row) => $row->seoable_type.' #'.$row->seoable_id],
            'yurba_revisions' => ['label' => 'Revision', 'column' => 'data', 'title' => fn ($row) => $row->revisionable_type.' #'.$row->revisionable_id],
        ];
        foreach ($side as $table => $conf) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach (DB::table($table)->where($conf['column'], 'like', '%'.$dir.'%')->lazyById(500) as $row) {
                    foreach ($this->extractRels((string) $row->{$conf['column']}, $dir) as $r) {
                        $index[$r][] = ['resource' => $conf['label'], 'title' => $conf['title']($row), 'url' => null];
                    }
                }
            } catch (\Throwable $e) {
                $this->scanFailed = true;
            }
        }

        foreach ([resource_path('views'), config_path()] as $root) {
            if (! is_dir($root)) {
                continue;
            }
            try {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    if (! $file->isFile() || $file->getSize() > 1_048_576) {
                        continue;
                    }
                    foreach ($this->extractRels((string) file_get_contents($file->getPathname()), $dir) as $r) {
                        $index[$r][] = ['resource' => 'File', 'title' => Str::after($file->getPathname(), base_path().DIRECTORY_SEPARATOR), 'url' => null];
                    }
                }
            } catch (\Throwable $e) {
                $this->scanFailed = true;
            }
        }

        foreach (static::$usageSources as $label => $source) {
            try {
                foreach ((array) $source() as $title => $text) {
                    foreach ($this->extractRels((string) (is_string($text) ? $text : json_encode($text)), $dir) as $r) {
                        $index[$r][] = ['resource' => (string) $label, 'title' => (string) $title, 'url' => null];
                    }
                }
            } catch (\Throwable $e) {
                $this->scanFailed = true;
            }
        }

        try {
            if (Schema::hasTable('yurba_content')) {
                foreach (DB::table('yurba_content')->get(['key', 'data']) as $row) {
                    foreach ($this->extractRels((string) $row->data, $dir) as $r) {
                        $index[$r][] = ['resource' => 'Content', 'title' => $row->key, 'url' => null];
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->scanFailed = true;
        }

        try {
            foreach (Store::all() as $key => $val) {
                if (is_string($val)) {
                    foreach ($this->extractRels($val, $dir) as $r) {
                        $index[$r][] = ['resource' => 'Settings', 'title' => (string) $key, 'url' => null];
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->scanFailed = true;
        }

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

    // /{dir}/x and its /{dir}-thumbs/{width}/x derivatives both count as a use of x
    protected function extractRels(string $text, string $dir): array
    {
        // unescape JSON-escaped slashes (\/media\/…) so content/repeater blobs match
        if (str_contains($text, '\\/')) {
            $text = str_replace('\\/', '/', $text);
        }

        if (! str_contains($text, '/'.$dir)) {
            return [];
        }

        preg_match_all('#/'.preg_quote($dir, '#').'(?:-thumbs/\d+)?/([A-Za-z0-9/_.\-]+\.[A-Za-z0-9]+)#', $text, $m);

        return array_values(array_unique($m[1] ?? []));
    }
}
