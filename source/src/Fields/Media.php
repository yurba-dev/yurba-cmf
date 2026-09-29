<?php

namespace Yurba\Cmf\Fields;

class Media extends Field
{
    public function indexComponent(): string
    {
        return 'image';
    }

    public function component(): string
    {
        return 'yurba::fields.media';
    }
}
