<?php

namespace Yurba\Cmf;

use Closure;
use Illuminate\Support\Collection;
use Yurba\Cmf\Resources\RedirectResource;
use Yurba\Cmf\Resources\Resource;
use Yurba\Cmf\Settings\PanelSettings;
use Yurba\Cmf\Settings\Store;

class Panel
{
    public const VERSION = '1.0.9';

    protected ?Closure $gate = null;

    public function brand(): string
    {
        return (string) Store::get('panel_brand', config('yurba.brand', 'YurbaCMF'));
    }

    public function logo(): string
    {
        return (string) (Store::get('panel_logo') ?: config('yurba.logo') ?: asset('vendor/yurba/yurba-logo.svg'));
    }

    public function hideBrandText(): bool
    {
        return (bool) Store::get('panel_hide_brand', config('yurba.hide_brand', false));
    }

    public function accent(): ?string
    {
        $value = (string) Store::get('panel_accent', '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
    }

    public function perPage(): int
    {
        return max(1, (int) Store::get('panel_per_page', config('yurba.per_page', 20)));
    }

    public function actionIcons(): bool
    {
        return (bool) Store::get('panel_action_icons', config('yurba.action_icons', false));
    }

    // A built-in icon: the site's own HTML from ui.icon_html by the Material Symbols name, else the symbol.
    // A value that is already markup (a row action's own icon) comes back as it is. $class goes on the
    // symbol, or on a span around the site's HTML.
    public function icon(string $name, string $class = ''): string
    {
        if (str_contains($name, '<')) {
            return $name;
        }
        $own = (array) config('yurba.ui.icon_html', []);
        if (filled($own[$name] ?? null)) {
            return $class === '' ? (string) $own[$name] : '<span class="'.e($class).'">'.$own[$name].'</span>';
        }

        return '<span class="material-symbols-rounded'.($class === '' ? '' : ' '.e($class)).'">'.e($name).'</span>';
    }

    public function mediaOptimize(): bool
    {
        return (bool) Store::get('panel_media_optimize', config('yurba.media.optimize', true));
    }

    public function locale(): string
    {
        $default = (string) config('yurba.locale', 'en');
        $available = array_keys((array) config('yurba.locales', ['en' => 'English']));
        $locale = (string) Store::get('panel_locale', $default);

        return in_array($locale, $available, true) ? $locale : $default;
    }

    public function locales(): array
    {
        return (array) config('yurba.locales', ['en' => 'English']);
    }

    protected ?string $contentLocale = null;

    public function multilangEnabled(): bool
    {
        return (bool) Store::get('panel_multilang', config('yurba.multilang.enabled', false))
            && count($this->contentLocales()) > 1;
    }

    public function contentLocales(): array
    {
        $stored = Store::get('panel_locales');
        $locales = is_array($stored) && $stored ? $stored : (array) config('yurba.multilang.locales', []);

        $out = [];
        foreach ($locales as $code => $def) {
            $code = (string) $code;
            if ($code == '') {
                continue;
            }
            $def = is_array($def) ? $def : ['label' => (string) $def];
            $out[$code] = [
                'label' => (string) ($def['label'] ?? $code),
                'slug' => trim((string) ($def['slug'] ?? $code), '/') ?: $code,
            ];
        }

        return $out;
    }

    public function defaultLocale(): string
    {
        $locales = $this->contentLocales();
        $default = (string) Store::get('panel_default_locale', config('yurba.multilang.default', 'en'));

        return isset($locales[$default]) ? $default : (string) array_key_first($locales);
    }

    public function contentLocale(): string
    {
        return $this->contentLocale ?? $this->defaultLocale();
    }

    public function setContentLocale(string $code): void
    {
        if (isset($this->contentLocales()[$code])) {
            $this->contentLocale = $code;
        }
    }

    public function localeSlug(string $code): string
    {
        return $this->contentLocales()[$code]['slug'] ?? $code;
    }

    public function localeBySlug(string $slug): ?string
    {
        foreach ($this->contentLocales() as $code => $def) {
            if ($def['slug'] == $slug) {
                return $code;
            }
        }

        return null;
    }

    public function styles(): array
    {
        return (array) config('yurba.styles', []);
    }

    public function scripts(): array
    {
        return (array) config('yurba.scripts', []);
    }

    public function head(): string
    {
        return (string) config('yurba.head', '');
    }

    public function foot(): string
    {
        return (string) config('yurba.foot', '');
    }

    public function prefix(): string
    {
        return trim((string) config('yurba.prefix', 'admin'), '/');
    }

    public function guard(): string
    {
        return (string) config('yurba.guard', 'web');
    }

    public function resources(): Collection
    {
        $resources = collect(config('yurba.resources', []))
            ->filter(fn ($class) => class_exists($class))
            ->map(fn ($class) => app($class));

        if (config('yurba.redirects.enabled', true)) {
            $resources->push(app(RedirectResource::class));
        }

        return $resources->values();
    }

    public function authorizedResources(): Collection
    {
        $user = $this->user();

        return $this->resources()
            ->filter(fn (Resource $r) => $r->canViewAny($user))
            ->values();
    }

    public function user(): mixed
    {
        return auth()->guard($this->guard())->user();
    }

    public function find(string $uriKey): ?Resource
    {
        return $this->resources()->first(fn (Resource $r) => $r->uriKey() == $uriKey);
    }

    public function settingsPages(): Collection
    {
        $pages = collect(config('yurba.settings', []))
            ->filter(fn ($class) => class_exists($class))
            ->map(fn ($class) => app($class));

        if (config('yurba.builtin_settings', true)) {
            $pages->prepend(app(PanelSettings::class));
        }

        return $pages->values();
    }

    public function findSettings(string $uriKey): ?Settings\SettingsPage
    {
        return $this->settingsPages()->first(fn ($p) => $p->uriKey() == $uriKey);
    }

    public function pages(): Collection
    {
        $user = $this->user();

        // a class-string, a [class, ...ctorArgs] tuple (keeps config:cache serializable), or a Page instance
        $pages = collect(config('yurba.pages', []))
            ->map(function ($page) {
                if ($page instanceof Pages\Page) {
                    return $page;
                }
                if (is_array($page)) {
                    $class = array_shift($page);

                    return class_exists($class) ? new $class(...$page) : null;
                }

                return is_string($page) && class_exists($page) ? app($page) : null;
            })
            ->filter(fn ($p) => $p instanceof Pages\Page);

        if (config('yurba.media.log', true)) {
            $pages->push(app(Pages\MediaOptimizationLogPage::class));
        }
        $pages->push(app(Pages\MediaUsagePage::class));
        $pages->push(app(Pages\LanguagesPage::class));

        return $pages
            ->filter(fn (Pages\Page $p) => $p->canView($user))
            ->values();
    }

    public function findPage(string $uriKey): ?Pages\Page
    {
        return $this->pages()->first(fn ($p) => $p->uriKey() == $uriKey);
    }

    public function authorizeUsing(Closure $callback): void
    {
        $this->gate = $callback;
    }

    public function authorize(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->gate) {
            return (bool) call_user_func($this->gate, $user);
        }

        return (bool) ($user->is_admin ?? false);
    }

    // the asset's mtime, so a refreshed build reaches browsers even if nobody bumps the constant
    public static function assetVersion(string $file, string $fallback = self::VERSION): string
    {
        $mtime = @filemtime(public_path('vendor/yurba/'.$file));

        return $mtime ? $fallback.'.'.$mtime : $fallback;
    }

    public function url(string $path = ''): string
    {
        return url($this->prefix().'/'.ltrim($path, '/'));
    }
}
