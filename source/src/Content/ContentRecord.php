<?php

namespace Yurba\Cmf\Content;

use Illuminate\Database\Eloquent\Model;

class ContentRecord extends Model
{
    protected $table = 'yurba_content';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];
}
