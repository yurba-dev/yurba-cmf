<?php

namespace Yurba\Cmf\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

// list filter declared on a Resource via filters(); its value arrives under the
// f[key] query param and constrains the index (and export) query
abstract class Filter
{
    public string $key;
    public string $label;

    public function __construct(string $key, ?string $label = null)
    {
        $this->key = $key;
        $this->label = $label ?? Str::headline($key);
    }

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label);
    }

    public function column(): string
    {
        return $this->key;
    }

    public function isActive(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    abstract public function apply(Builder $query, mixed $value): void;

    abstract public function component(): string;
}
