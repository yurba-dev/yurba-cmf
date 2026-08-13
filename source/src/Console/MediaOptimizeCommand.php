<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Yurba\Cmf\Media\ImageOptimizer;
use Yurba\Cmf\Media\Media;

// backfill the media library: thumbnails + downscale oversized originals
// (per config yurba.media.*). safe to re-run.
class MediaOptimizeCommand extends Command
{
    protected $signature = 'yurba:media-optimize
        {--force : Regenerate thumbnails even when one already exists}
        {--originals : Also re-encode originals that are not oversized (lossy)}';

    protected $description = 'Generate thumbnails and downscale oversized media library images';

    public function handle(): int
    {
        if (! ImageOptimizer::available()) {
            $this->components->error('GD is not available; cannot optimise images.');

            return self::FAILURE;
        }

        @ini_set('memory_limit', '1024M');

        $dir = trim((string) config('yurba.media.dir', 'media'), '/');
        $maxWidth = (int) config('yurba.media.max_width', 2560);
        $quality = (int) config('yurba.media.quality', 82);
        $thumbWidth = (int) config('yurba.media.thumb_width', 480);
        $force = (bool) $this->option('force');
        $originals = (bool) $this->option('originals');

        $resized = $thumbed = $skipped = $failed = 0;
        $bar = $this->output->createProgressBar(Media::count());

        Media::query()->orderBy('id')->chunkById(100, function ($rows) use (
            &$resized, &$thumbed, &$skipped, &$failed, $bar, $dir, $maxWidth, $quality, $thumbWidth, $force, $originals
        ) {
            foreach ($rows as $m) {
                $bar->advance();

                if (! $m->isImage() || ! Storage::disk($m->disk)->exists($m->path)) {
                    $skipped++;

                    continue;
                }

                try {
                    $data = Storage::disk($m->disk)->get($m->path);

                    $tooWide = $maxWidth > 0 && (int) $m->width > $maxWidth;
                    if (($tooWide || $originals) && ($opt = ImageOptimizer::process($data, $maxWidth, $quality))) {
                        [$newData, $w, $h] = $opt;
                        if ($tooWide || strlen($newData) < strlen($data)) {
                            Storage::disk($m->disk)->put($m->path, $newData);
                            $data = $newData;
                            $m->width = $w;
                            $m->height = $h;
                            $m->size = strlen($newData);
                            $resized++;
                        }
                    }

                    $needThumb = $force || ! $m->thumb_path || ! Storage::disk($m->disk)->exists((string) $m->thumb_path);
                    if ($needThumb && ($t = ImageOptimizer::thumbnail($data, $thumbWidth))) {
                        $thumbPath = $dir.'-thumbs/'.Str::after($m->path, $dir.'/');
                        Storage::disk($m->disk)->put($thumbPath, $t[0]);
                        $m->thumb_path = $thumbPath;
                        $thumbed++;
                    }

                    if ($m->isDirty()) {
                        $m->save();
                    }
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Resized: {$resized}, thumbnails: {$thumbed}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
