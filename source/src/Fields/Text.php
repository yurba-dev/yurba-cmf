<?php

namespace Yurba\Cmf\Fields;

class Text extends Field
{
    public string $type = 'text';

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function component(): string
    {
        return 'yurba::fields.text';
    }
}
