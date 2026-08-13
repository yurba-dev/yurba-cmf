<?php

namespace Yurba\Cmf\Fields;

use Illuminate\Database\Eloquent\Model;

class Date extends Field
{
    public bool $withTime = false;

    public function withTime(bool $v = true): static
    {
        $this->withTime = $v;

        return $this;
    }

    public function formValue(Model $model): mixed
    {
        $value = parent::formValue($model);
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->withTime ? 'Y-m-d\TH:i' : 'Y-m-d');
        }

        return $value;
    }

    public function indexValue(Model $model): string
    {
        $value = $this->value($model);
        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->withTime ? 'M j, Y H:i' : 'M j, Y');
        }

        return (string) ($value ?? '');
    }

    public function component(): string
    {
        return 'yurba::fields.date';
    }
}
