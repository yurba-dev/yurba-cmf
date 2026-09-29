<?php

namespace Yurba\Cmf\Filters;

use Illuminate\Database\Eloquent\Builder;

class DateRangeFilter extends Filter
{
    public function isActive(mixed $value): bool
    {
        return is_array($value) && (filled($value['from'] ?? null) || filled($value['to'] ?? null));
    }

    public function apply(Builder $query, mixed $value): void
    {
        if (filled($value['from'] ?? null)) {
            $query->whereDate($this->column(), '>=', $value['from']);
        }
        if (filled($value['to'] ?? null)) {
            $query->whereDate($this->column(), '<=', $value['to']);
        }
    }

    public function component(): string
    {
        return 'yurba::filters.date-range';
    }
}
