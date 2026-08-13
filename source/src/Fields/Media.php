<?php

namespace Yurba\Cmf\Fields;

// pick an asset from the media library; stores the chosen file's url as a string
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
