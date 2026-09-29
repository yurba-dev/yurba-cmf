<?php

namespace Yurba\Cmf\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Slug extends Field
{
    public ?string $baseUrl = null;

    public ?Closure $permalinkUsing = null;

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = false;
    }

    public function baseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');

        return $this;
    }

    public function permalink(Closure $resolver): static
    {
        $this->permalinkUsing = $resolver;

        return $this;
    }

    public function permalinkFor(Model $model): ?string
    {
        if (! $model->exists || blank($model->{$this->column()})) {
            return null;
        }

        if ($this->permalinkUsing) {
            return ($this->permalinkUsing)($model);
        }

        if ($this->baseUrl !== null) {
            return $this->baseUrl.'/'.$model->{$this->column()};
        }

        return null;
    }

    // blank: Sluggable derives it on create, the current slug stays on edit; a given value is normalised and made unique
    public function fill(Request $request, Model $model): void
    {
        $value = trim((string) $request->input($this->name, ''));
        if ($value == '') {
            return;
        }

        $model->{$this->column()} = $this->makeUnique($model, Str::slug($value));
    }

    protected function makeUnique(Model $model, string $slug): string
    {
        $column = $this->column();
        $base = $slug != '' ? $slug : $model->getKeyName();
        $slug = $base;
        $suffix = 1;

        while (
            $model->newQuery()
                ->where($column, $slug)
                ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function component(): string
    {
        return 'yurba::fields.slug';
    }

    public function indexComponent(): string
    {
        return 'slug';
    }
}
