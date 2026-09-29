<?php

namespace Yurba\Cmf\Filters;

use Illuminate\Database\Eloquent\Builder;

class SelectFilter extends Filter
{
    // value => label
    protected array $options = [];

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function isActive(mixed $value): bool
    {
        return is_scalar($value) && $value !== '';
    }

    public function apply(Builder $query, mixed $value): void
    {
        $query->where($this->column(), $value);
    }

    public function component(): string
    {
        return 'yurba::filters.select';
    }
}
