<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BelongsToMany extends Field
{
    public bool $virtual = true;

    public string $relatedModel;

    public string $titleColumn = 'name';

    protected ?string $relationName = null;

    protected ?array $optionCache = null;

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = false;
    }

    public function relatedModel(string $model): static
    {
        $this->relatedModel = $model;

        return $this;
    }

    public function title(string $column): static
    {
        $this->titleColumn = $column;

        return $this;
    }

    public function relation(string $method): static
    {
        $this->relationName = $method;

        return $this;
    }

    public function relationMethod(): string
    {
        return $this->relationName ?? $this->name;
    }

    public function options(): array
    {
        return $this->optionCache ??= $this->relatedModel::query()
            ->orderBy($this->titleColumn)
            ->pluck($this->titleColumn, (new $this->relatedModel)->getKeyName())
            ->all();
    }

    public function value(Model $model): mixed
    {
        if (! $model->exists) {
            return [];
        }

        return $model->{$this->relationMethod()}
            ->pluck((new $this->relatedModel)->getKeyName())
            ->all();
    }

    public function formValue(Model $model): mixed
    {
        return old($this->name, $this->value($model));
    }

    public function indexValue(Model $model): string
    {
        if (! $model->exists) {
            return '';
        }

        return $model->{$this->relationMethod()}->pluck($this->titleColumn)->implode(', ');
    }

    public function fill(Request $request, Model $model): void
    {
    }

    // sync the pivot once the record exists (empty selection detaches all)
    public function afterSave(Request $request, Model $model): void
    {
        $ids = array_values(array_filter((array) $request->input($this->name, []), fn ($v) => $v !== '' && $v !== null));

        $model->{$this->relationMethod()}()->sync($ids);
    }

    public function component(): string
    {
        return 'yurba::fields.belongs-to-many';
    }
}
