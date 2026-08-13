<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// comma-separated input backed by an array/json column
class Tags extends Field
{
    public function formValue(Model $model): mixed
    {
        $value = old($this->name, $model->exists ? $this->value($model) : $this->default);

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    public function fill(Request $request, Model $model): void
    {
        $raw = (string) $request->input($this->name);
        $model->{$this->column()} = collect(preg_split('/[,;|]+/', $raw))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values()
            ->all();
    }

    public function indexValue(Model $model): string
    {
        $value = $this->value($model);

        return Str::limit(is_array($value) ? implode(', ', $value) : (string) $value, 50);
    }

    public function component(): string
    {
        return 'yurba::fields.tags';
    }
}
