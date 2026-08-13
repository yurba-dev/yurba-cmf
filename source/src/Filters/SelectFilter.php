<?php

namespace Yurba\Cmf\Filters;

use Illuminate\Database\Eloquent\Builder;

// dropdown filter: one option matched with where(); also serves fks (id => label)
class SelectFilter extends Filter
{
    /** @var array<int|string, string> value => label */
    protected array $options = [];

    /** @param array<int|string, string> $options */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @return array<int|string, string> */
    public function getOptions(): array
    {
        return $this->options;
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
