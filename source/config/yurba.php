<?php

return [

    'brand' => env('YURBA_BRAND', 'YurbaCMF'),

    // image URL; null uses the bundled Yurba logo
    'logo' => env('YURBA_LOGO', null),

    'hide_brand' => env('YURBA_HIDE_BRAND', false),

    // panel interface language only; the site's content languages live under 'multilang'
    'locale' => env('YURBA_LOCALE', 'en'),

    'locales' => [
        'en' => 'English',
        'ru' => 'Русский',
        'uk' => 'Українська',
    ],

    'multilang' => [
        'enabled' => env('YURBA_MULTILANG', false),
        'default' => 'en',
        'locales' => [
            'en' => ['label' => 'English', 'slug' => 'en'],
        ],
    ],

    'prefix' => env('YURBA_PREFIX', 'admin'),

    'middleware' => ['web'],

    // panel entry defaults to users with a truthy is_admin; override via Yurba::authorizeUsing()
    'guard' => env('YURBA_GUARD', 'web'),

    'login_throttle' => [
        'max_attempts' => env('YURBA_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => env('YURBA_LOGIN_DECAY', 60),
    ],

    // classes extending Yurba\Cmf\Resources\Resource
    'resources' => [
    ],

    // classes extending Yurba\Cmf\Settings\SettingsPage
    'settings' => [
    ],

    'builtin_settings' => true,

    // classes extending Yurba\Cmf\Pages\Page
    'pages' => [
    ],

    'action_icons' => true,

    // publishes due 'scheduled' records every minute; the app's cron must run schedule:run
    'publish_scheduler' => true,

    // URLs: styles load in <head>, scripts before </body>
    'styles' => [],

    'scripts' => [],

    // raw HTML injected unescaped into <head> / before </body>
    'head' => null,

    'foot' => null,

    'per_page' => 20,

    // YurbaEditor inline images, stored under public/{dir}
    'uploads' => [
        'dir' => 'uploads/editor',
        'max_kb' => 4096,
        // no svg: it can carry scripts
        'mimes' => 'jpeg,jpg,png,gif,webp,avif',
    ],

    'media' => [
        'enabled' => env('YURBA_MEDIA', true),
        'disk' => env('YURBA_MEDIA_DISK', 'public'),
        // site-root-relative URLs survive a domain change; disable only for CDN/S3 disks that need absolute URLs
        'relative_urls' => env('YURBA_MEDIA_RELATIVE_URLS', true),
        'dir' => 'media',
        // no svg: it can carry scripts
        'mimes' => 'jpeg,jpg,png,gif,webp,avif,pdf',
        'max_kb' => 8192,
        'per_page' => 28,

        // re-encode uploads with GD: downscale to max_width and strip metadata
        'optimize' => env('YURBA_MEDIA_OPTIMIZE', true),
        'max_width' => 2560,
        'quality' => 82,
        // widths in px; thumbs are generated on demand and cached under {dir}-thumbs/{width}/
        'thumb_presets' => [
            'small' => 320,
            'medium' => 640,
            'large' => 1280,
        ],
        'thumb_default' => 'small',

        // record every optimization outcome; log_keep bounds the table (0 or less = unlimited)
        'log' => env('YURBA_MEDIA_LOG', true),
        'log_keep' => env('YURBA_MEDIA_LOG_KEEP', 1000),
    ],

    'redirects' => [
        'enabled' => env('YURBA_REDIRECTS', true),
        'log_hits' => true,
    ],

    // public /sitemap.xml outside the admin gate; records marked noindex are skipped
    'sitemap' => [
        'enabled' => env('YURBA_SITEMAP', true),
        'static' => [],
        // entries: model, route, param, key, plus optional scope, filter, lastmod, changefreq, priority
        'sources' => [],
    ],

    'editor' => [
        // 'yurba' (bundled), 'none' (plain textarea) or 'custom' (styles/scripts plus an init snippet for textarea[data-editor])
        'driver' => env('YURBA_EDITOR', 'yurba'),
        'styles' => [],
        'scripts' => [],
        'init' => null,
    ],

    'ui' => [
        'select' => env('YURBA_UI_SELECT', true),
        // click-to-zoom image previews via YurbaPV
        'viewer' => env('YURBA_UI_VIEWER', true),
        // Material Symbols Rounded font; disable to self-host or bring your own icons via 'styles' / 'head'
        'icons' => env('YURBA_UI_ICONS', true),
    ],
];
