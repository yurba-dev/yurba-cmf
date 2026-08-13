<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    | 'brand' — text shown in the sidebar / login.
    | 'logo'  — image URL shown next to the brand. Null falls back to the
    |           bundled Yurba logo; set it to your own asset to override.
    */
    'brand' => env('YURBA_BRAND', 'YurbaCMF'),

    'logo' => env('YURBA_LOGO', null),

    // Show only the logo in the sidebar, hiding the brand-name text. The brand
    // is still used in page titles. Togglable from the built-in Panel settings.
    'hide_brand' => env('YURBA_HIDE_BRAND', false),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | URL prefix and middleware for the admin panel. The 'yurba.auth' middleware
    | (registered by the package) enforces the authorization gate below.
    */
    'prefix' => env('YURBA_PREFIX', 'admin'),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    | 'guard'   — the auth guard used to sign in to the panel.
    | 'gate'    — a callback (auth user) => bool deciding who may enter. Override
    |             it in your app's service provider via Yurba::authorizeUsing().
    |             By default anyone with a truthy `is_admin` attribute is allowed.
    */
    'guard' => env('YURBA_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | Login rate limiting
    |--------------------------------------------------------------------------
    | Failed panel-login attempts are throttled per email+IP (cache-backed, no
    | table). After 'max_attempts' fails the account is locked for
    | 'decay_seconds'; a successful login clears the counter.
    */
    'login_throttle' => [
        'max_attempts' => env('YURBA_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => env('YURBA_LOGIN_DECAY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    | The CRUD resources exposed by the panel — each a class extending
    | Yurba\Cmf\Resources\Resource.
    */
    'resources' => [
        // App\Admin\JobResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings pages
    |--------------------------------------------------------------------------
    | Your own settings pages (classes extending Yurba\Cmf\Settings\SettingsPage).
    | The built-in "Panel" settings page (branding, accent, pagination) is added
    | automatically — set 'builtin_settings' to false to hide it.
    */
    'settings' => [
        // App\Admin\Settings\GeneralSettings::class,
    ],

    'builtin_settings' => true,

    /*
    |--------------------------------------------------------------------------
    | Custom pages
    |--------------------------------------------------------------------------
    | Your own panel screens (classes extending Yurba\Cmf\Pages\Page). Each gets
    | a sidebar entry and renders inside the panel shell — for bespoke editors,
    | dashboards or reports that aren't a plain CRUD resource.
    */
    'pages' => [
        // App\Admin\Pages\ContentPage::class,
    ],

    // Render the table row actions (View / Edit / Delete …) as icons instead of
    // text. On by default (the icon font ships with the panel); togglable at
    // runtime from the built-in Panel settings page.
    'action_icons' => true,

    // Register the every-minute scheduler that publishes due 'scheduled' records
    // (per each resource's publishing()). The app's cron must run schedule:run.
    'publish_scheduler' => true,

    /*
    |--------------------------------------------------------------------------
    | Extra assets (your own dependencies)
    |--------------------------------------------------------------------------
    | Add stylesheet / script URLs to load inside the panel — e.g. an icon font
    | for menu icons. They are injected into the panel's <head> / before </body>.
    |
    |   'styles' => ['https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined'],
    */
    // Stylesheet URLs (loaded in <head>) and script URLs (loaded before </body>).
    'styles' => [],

    'scripts' => [],

    // Raw HTML injected verbatim into <head> / before </body> — for anything the
    // arrays above can't express (inline <style>, <meta>, inline <script>, …).
    'head' => null,

    'foot' => null,

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'per_page' => 20,

    /*
    |--------------------------------------------------------------------------
    | Editor uploads
    |--------------------------------------------------------------------------
    |
    | Inline image uploads from the YurbaEditor field. Files are moved under
    | public/{dir} and served from /{dir}. SVG is intentionally excluded from
    | the mime list (it can carry scripts).
    */
    'uploads' => [
        'dir' => 'uploads/editor',
        'max_kb' => 4096,
        'mimes' => 'jpeg,jpg,png,gif,webp,avif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media library
    |--------------------------------------------------------------------------
    |
    | A central asset manager (sidebar "Media" tab) backed by a Storage disk, so
    | the same setup works on local or S3. Set 'enabled' to false to hide it.
    | SVG is left out of the default mimes (it can carry scripts).
    */
    'media' => [
        'enabled' => env('YURBA_MEDIA', true),
        'disk' => env('YURBA_MEDIA_DISK', 'public'),
        // store site-root-relative URLs (/storage/…) so picked media survives a
        // domain change; set false only for external CDN/S3 disks needing absolute URLs
        'relative_urls' => env('YURBA_MEDIA_RELATIVE_URLS', true),
        'dir' => 'media',
        'mimes' => 'jpeg,jpg,png,gif,webp,avif,pdf',
        'max_kb' => 8192,
        'per_page' => 28,

        // Re-encode uploads (GD, no extra dependency): downscale to max_width and
        // strip metadata. thumbnails generates a small derivative for grids/picker.
        'optimize' => env('YURBA_MEDIA_OPTIMIZE', true),
        'max_width' => 2560,
        'quality' => 82,
        'thumbnails' => true,
        'thumb_width' => 480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | An admin-managed URL redirect table (sidebar "Redirects" tab, backed by the
    | yurba_redirects table). Global middleware applies them — even for paths that
    | no longer have a route. 'log_hits' counts each redirect's usage.
    */
    'redirects' => [
        'enabled' => env('YURBA_REDIRECTS', true),
        'log_hits' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | A public XML sitemap at /sitemap.xml (outside the admin gate). 'static' adds
    | URLs verbatim; each 'sources' entry maps a content model to its public
    | route. Records whose SEO block is marked noindex are excluded automatically.
    |
    |   'sources' => [[
    |       'model'   => App\Models\Article::class,
    |       'route'   => 'news.show',   // named route
    |       'param'   => 'article',     // route parameter name
    |       'key'     => 'slug',        // attribute bound to that parameter
    |       'scope'   => 'published',   // optional query scope(s) for visibility
    |       'filter'  => [],            // optional where-equals map
    |       'lastmod' => 'updated_at',  // column used for <lastmod>
    |       'changefreq' => 'weekly',
    |       'priority'   => '0.7',
    |   ]],
    */
    'sitemap' => [
        'enabled' => env('YURBA_SITEMAP', true),
        'static' => [],
        'sources' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor driver
    |--------------------------------------------------------------------------
    |
    | Which library powers the Editor (rich-text) field:
    |   'yurba'  — the bundled YurbaEditor (default)
    |   'none'   — a plain <textarea>, no JS
    |   'custom' — bring your own: list asset URLs in styles/scripts and an
    |              init snippet that enhances `textarea[data-editor]`.
    */
    'editor' => [
        'driver' => env('YURBA_EDITOR', 'yurba'),
        'styles' => [],
        'scripts' => [],
        'init' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI enhancements (YurbaUI)
    |--------------------------------------------------------------------------
    |
    | Progressive enhancements layered over native controls. Disable any of them
    | to fall back to the plain HTML control.
    */
    'ui' => [
        'select' => env('YURBA_UI_SELECT', true),
        // Click image previews/thumbnails to open them fullscreen in YurbaPV.
        'viewer' => env('YURBA_UI_VIEWER', true),
        // Load the Material Symbols Rounded icon font (used by sidebar icons,
        // resource icon()s and — when enabled — the row action buttons). Turn it
        // off to self-host or supply your own icon set via 'styles' / 'head'.
        'icons' => env('YURBA_UI_ICONS', true),
    ],
];
