<?php

namespace Yurba\Cmf\Filters;

use Illuminate\Database\Eloquent\Builder;

class BooleanFilter extends SelectFilter
{
    public function getOptions(): array
    {
        return ['1' => 'Yes', '0' => 'No'];
    }

    public function apply(Builder $query, mixed $value): void
    {
        $query->where($this->column(), (string) $value === '1');
    }
}
