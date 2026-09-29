<?php

use Yurba\Cmf\Content\Content;

if (! function_exists('content')) {
    function content(string $page, ?string $field = null, mixed $default = null): mixed
    {
        return $field === null
            ? Content::get($page)
            : Content::field($page, $field, $default);
    }
}
