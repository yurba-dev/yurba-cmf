<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Number extends Text
{
    public string $type = 'number';

    public function fill(Request $request, Model $model): void
    {
        $value = $request->input($this->name);
        $model->{$this->column()} = ($value === null || $value === '') ? null : $value + 0;
    }
}
