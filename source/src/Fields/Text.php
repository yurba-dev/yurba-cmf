<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Text extends Field
{
    public string $type = 'text';

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    // a password box never echoes the stored hash, and left blank it keeps it (re-saving the hash would hash it again through a mutator)
    public function formValue(Model $model): mixed
    {
        return $this->type == 'password' ? '' : parent::formValue($model);
    }

    public function fill(Request $request, Model $model): void
    {
        if ($this->type == 'password' && blank($request->input($this->name))) {
            return;
        }

        parent::fill($request, $model);
    }

    public function component(): string
    {
        return 'yurba::fields.text';
    }
}
