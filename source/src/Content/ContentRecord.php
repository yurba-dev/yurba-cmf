<?php

namespace Yurba\Cmf\Content;

use Illuminate\Database\Eloquent\Model;

// backing row for one content page: key + a json field-value document
class ContentRecord extends Model
{
    protected $table = 'yurba_content';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];
}
