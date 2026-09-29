<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// stored as flat seo_* keys in the page's content json; SeoField uses the yurba_seo table instead
class ContentSeo extends Field
{
    protected const KEYS = [
        'meta_title' => 'seo_title',
        'meta_description' => 'seo_description',
        'og_image' => 'seo_og_image',
        'noindex' => 'seo_noindex',
    ];

    public function __construct(string $name = 'seo', ?string $label = 'SEO')
    {
        parent::__construct($name, $label);
        $this->onIndex = false;
    }

    public static function make(string $name = 'seo', ?string $label = 'SEO'): static
    {
        return new static($name, $label);
    }

    public function hydrate(Model $record, array $stored): void
    {
        foreach (static::KEYS as $col) {
            $record->{$col} = $stored[$col] ?? null;
        }
    }

    public function formValue(Model $model): mixed
    {
        $old = old($this->name);
        if (is_array($old)) {
            return $old;
        }

        $out = [];
        foreach (static::KEYS as $blockField => $col) {
            $out[$blockField] = $model->{$col} ?? null;
        }

        return $out;
    }

    public function fill(Request $request, Model $model): void
    {
        $data = (array) $request->input($this->name, []);

        $model->seo_title = $data['meta_title'] ?? null;
        $model->seo_description = $data['meta_description'] ?? null;
        $model->seo_og_image = $data['og_image'] ?? null;
        $model->seo_noindex = ! empty($data['noindex']);
    }

    public function component(): string
    {
        return 'yurba::fields.seo';
    }
}
