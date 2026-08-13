# YurbaCMF

A lightweight, dependency-free auto-CRUD admin panel and content framework for Laravel. The whole interface is plain Blade and vanilla CSS — no front-end build step and nothing extra to compile. Declare a model and its fields, and the panel generates the list, forms, validation and persistence.

## Features

- Auto-CRUD: list / create / edit / show / delete from a resource class
- 17 field types, form tabs & sections, conditional fields, validation rules
- List filters, global search, sortable/searchable columns, CSV import/export
- Media library + picker, dependency-free image optimisation (GD) + thumbnails
- Revisions/versioning, scheduled publishing + signed draft preview
- SEO meta fields (polymorphic) + public XML sitemap
- URL redirect manager with global middleware
- Per-resource, per-action authorization (Laravel policies) + soft-delete trash
- Built-in dashboard, settings pages, and generator commands

## Requirements

- PHP 8.2+
- Laravel 11+
- GD extension (for image optimisation; without it, uploads are stored as-is)

## Install

```bash
composer require yurba/cmf
php artisan yurba:install   # publishes config + assets, creates app/Admin
php artisan migrate         # media, revisions, seo, redirects tables
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

See the [documentation](https://yurba-dev.github.io/yurba-cmf/) for the full field reference, filters, actions, revisions, SEO, media, authorization and configuration.