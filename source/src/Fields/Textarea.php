<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Textarea extends Field
{
    public int $rows = 5;

    public function __construct(string $name, ?string $label = null)
    {
        parent::__construct($name, $label);
        $this->onIndex = false; // long text stays off the table by default
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function indexValue(Model $model): string
    {
        return Str::limit(strip_tags((string) $this->value($model)), 60);
    }

    public function component(): string
    {
        return 'yurba::fields.textarea';
    }
}
