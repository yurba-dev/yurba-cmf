<?php

namespace Yurba\Cmf\Translations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $locale
 * @property string $field
 * @property ?string $value
 */
class Translation extends Model
{
    protected $table = 'yurba_translations';

    public $timestamps = false;

    protected $guarded = [];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
