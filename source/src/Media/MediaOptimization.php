<?php

namespace Yurba\Cmf\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * one image-optimization event, shown in the Optimization log screen.
 *
 * @property string $path
 * @property string $source
 * @property string $status
 * @property ?string $reason
 * @property bool $optimized
 * @property bool $thumbnailed
 * @property ?int $width
 * @property ?int $height
 * @property int $orig_size
 * @property int $new_size
 */
class MediaOptimization extends Model
{
    public const STATUS_OPTIMIZED = 'optimized';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_UNCHANGED = 'unchanged';

    protected $table = 'yurba_media_optimizations';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'optimized' => 'boolean',
        'thumbnailed' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'orig_size' => 'integer',
        'new_size' => 'integer',
        'created_at' => 'datetime',
    ];

    // enabled unless explicitly turned off
    public static function enabled(): bool
    {
        return (bool) config('yurba.media.log', true);
    }

    // record one processed image; best-effort, never breaks the caller.
    public static function record(array $attributes): void
    {
        if (! static::enabled()) {
            return;
        }

        try {
            if (! Schema::hasTable((new static)->getTable())) {
                return;
            }

            static::create($attributes + ['created_at' => now()]);
            static::prune();
        } catch (\Throwable $e) {
        }
    }

    // derive [status, reason] from the source bytes and outcome.
    public static function classify(string $origData, bool $optimizeEnabled, bool $optimized, bool $thumbnailed): array
    {
        $i = ImageOptimizer::inspect($origData);

        if (! $i['raster']) {
            return [self::STATUS_SKIPPED, 'Not a raster image — GD cannot optimize it.'];
        }

        if (! $optimizeEnabled) {
            return [self::STATUS_SKIPPED, 'Image optimization is turned off in settings.'];
        }

        if ($i['over_limit']) {
            $mp = round($i['pixels'] / 1_000_000, 1);
            $max = round($i['max_pixels'] / 1_000_000);

            return [self::STATUS_SKIPPED, "Too large: {$mp} MP exceeds the current ~{$max} MP memory budget."];
        }

        if ($optimized || $thumbnailed) {
            return [self::STATUS_OPTIMIZED, null];
        }

        return [self::STATUS_UNCHANGED, null];
    }

    // percentage the original shrank by (0 when it grew or is unknown)
    public function savingsPercent(): int
    {
        if ($this->orig_size <= 0 || $this->new_size <= 0 || $this->new_size >= $this->orig_size) {
            return 0;
        }

        return (int) round(100 - ($this->new_size / $this->orig_size * 100));
    }

    // how many recent events to retain (0 or less = unlimited)
    public static function keep(): int
    {
        return (int) config('yurba.media.log_keep', 1000);
    }

    // wipe the whole log
    public static function clear(): void
    {
        static::query()->delete();
    }

    // keep the table bounded to the newest keep() rows.
    protected static function prune(): void
    {
        $keep = static::keep();
        if ($keep <= 0) {
            return;
        }

        $cutoff = static::query()->orderByDesc('id')->skip($keep)->value('id');

        if ($cutoff) {
            static::query()->where('id', '<=', $cutoff)->delete();
        }
    }
}
