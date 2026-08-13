<?php

namespace Yurba\Cmf\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * seo metadata for one record; read on the frontend via Seo::resolve().
 *
 * @property ?string $meta_title
 * @property ?string $meta_description
 * @property ?string $og_image
 * @property bool $noindex
 */
class Seo extends Model
{
    protected $table = 'yurba_seo';

    protected $guarded = [];

    protected $casts = [
        'noindex' => 'boolean',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function for(Model $model): ?self
    {
        return static::query()
            ->where('seoable_type', $model->getMorphClass())
            ->where('seoable_id', $model->getKey())
            ->first();
    }

    /**
     * @param  array{title?: string, description?: string, image?: string}  $fallback
     * @return array{title: ?string, description: ?string, image: ?string, noindex: bool}
     */
    public static function resolve(Model $model, array $fallback = []): array
    {
        $seo = static::for($model);

        return [
            'title' => $seo?->meta_title ?: ($fallback['title'] ?? null),
            'description' => $seo?->meta_description ?: ($fallback['description'] ?? null),
            'image' => $seo?->og_image ?: ($fallback['image'] ?? null),
            'noindex' => (bool) ($seo?->noindex ?? false),
        ];
    }
}
