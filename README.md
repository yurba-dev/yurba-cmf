# YurbaCMF

A lightweight, dependency-free auto-CRUD admin panel and content framework for Laravel. The whole interface is plain Blade and vanilla CSS — no front-end build step and nothing extra to compile. Declare a model and its fields, and the panel generates the list, forms, validation and persistence.

## Features

- Auto-CRUD: list / create / edit / show / delete from a resource class
- 17 field types, form tabs & sections, conditional fields, validation rules
- List filters, global search, sortable/searchable columns, CSV import/export
- Media library + picker, dependency-free image optimisation (GD) + on-demand thumbnails
- Revisions/versioning, scheduled publishing + signed draft preview
- SEO meta fields (polymorphic) + public XML sitemap (single or multi-file index)
- URL redirect manager with global middleware
- Per-resource, per-action authorization (Laravel policies) + soft-delete trash
- Translatable content: per-locale model fields (`->translatable()`) in a translations table
- Translatable panel interface (English built in, Russian & Ukrainian bundled)
- Built-in dashboard, settings pages, and generator commands

## Requirements

- PHP 8.2+
- Laravel 11+
- GD extension (for image optimisation; without it, uploads are stored as-is)

## Install

```bash
composer require yurba/cmf
php artisan yurba:install   # publishes config + assets, creates app/Admin
php artisan migrate         # media, revisions, seo, redirects, content, translations, optimization-log tables
```

Gate who may enter the panel (any truthy check) in a service provider:

```php
use Yurba\Cmf\Facades\Yurba;

Yurba::authorizeUsing(fn ($user) => $user->is_admin);
```

The panel lives at `/admin` (change the prefix in `config/yurba.php`).

## Define a resource

```php
namespace App\Admin;

use App\Models\Post;
use Yurba\Cmf\Fields\Text;
use Yurba\Cmf\Fields\Editor;
use Yurba\Cmf\Fields\Slug;
use Yurba\Cmf\Resources\Resource;

class PostResource extends Resource
{
    public static string $model = Post::class;

    public function fields(): array
    {
        return [
            Text::make('title')->searchable()->sortable()->rules('required|string|max:255'),
            Slug::make('slug')->baseUrl(url('/blog')),
            Editor::make('body')->onlyOnForm(),
        ];
    }
}
```

Register it in `config/yurba.php` under `resources` (or scaffold with `php artisan yurba:resource Post --from-schema --register`).

## Translatable content

Add the `HasTranslations` trait to a model and mark any field `->translatable()`. The form shows a language bar and stores per-locale values in `yurba_translations` (the base row keeps the default locale); read the current-locale value with `$model->tr('field')`, which falls back to the base. Enable languages and pick the default in `config/yurba.php` → `multilang`.

```php
use Yurba\Cmf\Translations\HasTranslations;

class Post extends Model
{
    use HasTranslations;
}

// in the resource
Text::make('title')->translatable();

// on the frontend
$post->tr('title');
```

See the [documentation](https://yurba-dev.github.io/yurba-cmf/) for the full field reference, filters, actions, revisions, SEO, media, authorization and configuration.