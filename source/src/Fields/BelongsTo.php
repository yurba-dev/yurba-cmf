<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;

class BelongsTo extends Field
{
    public string $relatedModel;

    public string $titleColumn = 'name';

    public bool $nullable = false;

    protected ?array $optionCache = null;

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

    public function nullable(bool $v = true): static
    {
        $this->nullable = $v;

        return $this;
    }

    public function options(): array
    {
        return $this->optionCache ??= $this->relatedModel::query()
            ->orderBy($this->titleColumn)
            ->pluck($this->titleColumn, (new $this->relatedModel)->getKeyName())
            ->all();
    }

    public function indexValue(Model $model): string
    {
        $id = $this->value($model);

        return (string) ($this->options()[$id] ?? '');
    }

    public function indexComponent(): string
    {
        return 'badge';
    }

    public function component(): string
    {
        return 'yurba::fields.belongs-to';
    }
}
