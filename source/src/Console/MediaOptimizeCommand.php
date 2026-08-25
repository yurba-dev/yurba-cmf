<?php

namespace Yurba\Cmf\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Yurba\Cmf\Media\ImageOptimizer;
use Yurba\Cmf\Media\Media;
use Yurba\Cmf\Media\MediaOptimization;

// downscale oversized media originals (per config yurba.media.*). safe to re-run.
class MediaOptimizeCommand extends Command
{
    protected $signature = 'yurba:media-optimize
        {--originals : Also re-encode originals that are not oversized (lossy)}';

    protected $description = 'Downscale oversized media library images (thumbnails are generated on demand)';

    public function handle(): int
    {
        if (! ImageOptimizer::available()) {
            $this->components->error('GD is not available; cannot optimise images.');

            return self::FAILURE;
        }

        @ini_set('memory_limit', '1024M');

        $maxWidth = (int) config('yurba.media.max_width', 2560);
        $quality = (int) config('yurba.media.quality', 82);
        $originals = (bool) $this->option('originals');

        $resized = $skipped = $failed = 0;
        $bar = $this->output->createProgressBar(Media::count());

        Media::query()->orderBy('id')->chunkById(100, function ($rows) use (
            &$resized, &$skipped, &$failed, $bar, $maxWidth, $quality, $originals
        ) {
            foreach ($rows as $m) {
                $bar->advance();

                if (! $m->isImage() || ! Storage::disk($m->disk)->exists($m->path)) {
                    $skipped++;

                    continue;
                }

                try {
                    $data = $origData = Storage::disk($m->disk)->get($m->path);
                    $origSize = strlen($origData);
                    $optimized = false;

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
                            $optimized = true;
                        }
                    }

                    if ($m->isDirty()) {
                        $m->save();
                    }

                    if ($m->isImage()) {
                        [$status, $reason] = MediaOptimization::classify($origData, true, $optimized, false);
                        MediaOptimization::record([
                            'path' => $m->path,
                            'source' => 'command',
                            'status' => $status,
                            'reason' => $reason,
                            'optimized' => $optimized,
                            'thumbnailed' => false,
                            'width' => $m->width,
                            'height' => $m->height,
                            'orig_size' => $origSize,
                            'new_size' => strlen($data),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Resized: {$resized}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }
}
