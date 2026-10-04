<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;

class Select extends Field
{
    // bump on yurba-ui update
    public const UI_ASSET_VERSION = '1.0.3';

    // value => label
    public array $options = [];

    public bool $nullable = false;

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function nullable(bool $v = true): static
    {
        $this->nullable = $v;

        return $this;
    }

    // an enum-cast attribute is an object: as an options key or a string it throws
    public function value(Model $model): mixed
    {
        $value = parent::value($model);

        return $value instanceof \UnitEnum ? ($value instanceof \BackedEnum ? $value->value : $value->name) : $value;
    }

    public function indexValue(Model $model): string
    {
        $value = $this->value($model);

        return (string) ($this->options[$value] ?? $value ?? '');
    }

    public function indexComponent(): string
    {
        return 'badge';
    }

    public function component(): string
    {
        return 'yurba::fields.select';
    }
}
