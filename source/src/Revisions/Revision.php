<?php

namespace Yurba\Cmf\Revisions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * immutable snapshot of a record's attributes at save time.
 *
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property array $data
 * @property ?int $user_id
 * @property ?string $user_name
 */
class Revision extends Model
{
    protected $table = 'yurba_revisions';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    // changed keys vs another attribute set (compact diff)
    public function changedFrom(array $other): array
    {
        $changed = [];
        foreach ($this->data as $key => $value) {
            if (($other[$key] ?? null) != $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
