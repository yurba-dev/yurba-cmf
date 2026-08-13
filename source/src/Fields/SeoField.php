<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Yurba\Cmf\Seo\Seo;

// manages a record's seo block (meta title/description/og image/noindex) in the
// polymorphic yurba_seo table, so no columns on the content table. persisted in afterSave()
class SeoField extends Field
{
    public bool $virtual = true;

    public function __construct(string $name = 'seo', ?string $label = 'SEO')
    {
        parent::__construct($name, $label);
        $this->onIndex = false;
    }

    public static function make(string $name = 'seo', ?string $label = 'SEO'): static
    {
        return new static($name, $label);
    }

    /** @return array<string, mixed> the current SEO values */
    public function formValue(Model $model): mixed
    {
        $old = old($this->name);
        if (is_array($old)) {
            return $old;
        }

        $seo = $model->exists ? Seo::for($model) : null;

        return $seo
            ? $seo->only(['meta_title', 'meta_description', 'og_image', 'noindex'])
            : [];
    }

    public function fill(Request $request, Model $model): void
    {
        // stored after save (needs the record to exist)
    }

    public function afterSave(Request $request, Model $model): void
    {
        $data = (array) $request->input($this->name, []);

        Seo::updateOrCreate(
            ['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->getKey()],
            [
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'og_image' => $data['og_image'] ?? null,
                'noindex' => ! empty($data['noindex']),
            ]
        );
    }

    public function component(): string
    {
        return 'yurba::fields.seo';
    }
}
