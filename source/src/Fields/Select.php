<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;

class Select extends Field
{
    // bump on yurba-ui update
    public const UI_ASSET_VERSION = '1.0.0';

    /** @var array<string|int, string> value => label */
    public array $options = [];

    public bool $nullable = false;

    /** @param array<string|int, string> $options value => label (e.g. [0 => 'Draft', 1 => 'Published']) */
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
