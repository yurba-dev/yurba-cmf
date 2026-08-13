<?php

use Yurba\Cmf\Content\Content;

if (! function_exists('content')) {
    // frontend read for content pages: content('home') => all values,
    // content('home', 'hero_title', 'fallback') => one value
    function content(string $page, ?string $field = null, mixed $default = null): mixed
    {
        return $field === null
            ? Content::get($page)
            : Content::field($page, $field, $default);
    }
}
