<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// a model attribute rendered as a form control + table cell, with its rules
abstract class Field
{
    public string $name;
    public string $label;
    public array $rules = [];
    public bool $sortable = false;
    public bool $searchable = false;
    public bool $onIndex = true;
    public bool $onForm = true;
    public ?string $placeholder = null;
    public mixed $default = null;
    public ?string $help = null;

    // optional links shown as buttons under the help text: [['url' => .., 'label' => ..], ...]
    public array $helpLinks = [];

    // preferred thumbnail size for image fields: preset name or pixel width; null = original.
    public int|string|null $thumb = null;

    // form grouping: a tab and/or a titled section
    public ?string $tab = null;
    public ?string $section = null;

    // rendered read-only, skipped on save
    public bool $readonly = false;

    // display-only, never validated or persisted
    public bool $virtual = false;

    // show-when: ['field' => name, 'values' => string[]]
    public ?array $condition = null;

    public function __construct(string $name, ?string $label = null)
    {
        $this->name = $name;
        $this->label = $label ?? Str::headline($name);
    }

    public static function make(string $name, ?string $label = null): static
    {
        return new static($name, $label);
    }

    public function rules(array|string $rules): static
    {
        $this->rules = is_array($rules) ? $rules : explode('|', $rules);

        return $this;
    }

    public function sortable(bool $v = true): static
    {
        $this->sortable = $v;

        return $this;
    }

    public function searchable(bool $v = true): static
    {
        $this->searchable = $v;

        return $this;
    }

    public function onlyOnForm(): static
    {
        $this->onIndex = false;
        $this->onForm = true;

        return $this;
    }

    public function onlyOnIndex(): static
    {
        $this->onForm = false;
        $this->onIndex = true;

        return $this;
    }

    public function hideFromIndex(): static
    {
        $this->onIndex = false;

        return $this;
    }

    public function placeholder(string $p): static
    {
        $this->placeholder = $p;

        return $this;
    }

    public function default(mixed $v): static
    {
        $this->default = $v;

        return $this;
    }

    public function help(string $h): static
    {
        $this->help = $h;

        return $this;
    }

    // add a button-style link under the help text (call more than once for several)
    public function helpLink(string $url, string $label): static
    {
        $this->helpLinks[] = ['url' => $url, 'label' => $label];

        return $this;
    }

    // request a thumbnail size for this image field (preset name or pixel width).
    public function thumb(int|string $size): static
    {
        $this->thumb = $size;

        return $this;
    }

    public function tab(string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    public function section(string $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function readonly(bool $v = true): static
    {
        $this->readonly = $v;

        return $this;
    }

    // booleans normalise to '1'/'0' to match the submitted checkbox value
    public function visibleWhen(string $field, mixed $values): static
    {
        $values = is_array($values) ? $values : [$values];

        $this->condition = [
            'field' => $field,
            'values' => array_map(
                fn ($v) => $v === true ? '1' : ($v === false ? '0' : (string) $v),
                $values
            ),
        ];

        return $this;
    }

    public function passesCondition(array $input): bool
    {
        if ($this->condition === null) {
            return true;
        }

        $actual = $input[$this->condition['field']] ?? null;
        $actual = $actual === null ? '' : (string) $actual;

        return in_array($actual, $this->condition['values'], true);
    }

    public function column(): string
    {
        return $this->name;
    }

    public function value(Model $model): mixed
    {
        return $model->{$this->column()};
    }

    public function indexValue(Model $model): string
    {
        return (string) ($this->value($model) ?? '');
    }

    public function formValue(Model $model): mixed
    {
        return old($this->name, $model->exists ? $this->value($model) : $this->default);
    }

    public function fill(Request $request, Model $model): void
    {
        $model->{$this->column()} = $request->input($this->name);
    }

    // runs after save; relation fields sync pivots here (needs the record to exist)
    public function afterSave(Request $request, Model $model): void
    {
    }

    abstract public function component(): string;

    // index cell renderer: text | boolean | badge | image
    public function indexComponent(): string
    {
        return 'text';
    }

    public function isHtml(): bool
    {
        return false;
    }
}
