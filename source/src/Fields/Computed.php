<?php

namespace Yurba\Cmf\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

// display-only field derived from the record via a using() callback; never persisted
class Computed extends Field
{
    public bool $virtual = true;

    protected ?Closure $callback = null;

    // resolver: fn (Model $record): mixed
    public function using(Closure $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    public function value(Model $model): mixed
    {
        if ($this->callback) {
            return ($this->callback)($model);
        }

        return $model->{$this->name} ?? null;
    }

    public function formValue(Model $model): mixed
    {
        return $model->exists ? $this->value($model) : $this->default;
    }

    public function fill(Request $request, Model $model): void
    {
    }

    public function component(): string
    {
        return 'yurba::fields.computed';
    }
}
