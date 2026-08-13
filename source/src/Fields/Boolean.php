<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Boolean extends Field
{
    public function formValue(Model $model): mixed
    {
        return (bool) old($this->name, $model->exists ? $this->value($model) : $this->default);
    }

    public function fill(Request $request, Model $model): void
    {
        $model->{$this->column()} = $request->boolean($this->name);
    }

    public function component(): string
    {
        return 'yurba::fields.boolean';
    }

    public function indexComponent(): string
    {
        return 'boolean';
    }
}
