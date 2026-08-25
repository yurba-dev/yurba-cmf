<?php

namespace Yurba\Cmf;

use Closure;
use Illuminate\Support\Collection;
use Yurba\Cmf\Resources\RedirectResource;
use Yurba\Cmf\Resources\Resource;
use Yurba\Cmf\Settings\PanelSettings;
use Yurba\Cmf\Settings\Store;

// registry of the registered CRUD resources; decides who may enter the admin.
// bound as a singleton ("yurba.cmf").
class Panel
{
    // YurbaCMF release, shown in the panel footer
    public const VERSION = '1.0.2';

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

    public function mediaOptimize(): bool
    {
        return (bool) Store::get('panel_media_optimize', config('yurba.media.optimize', true));
    }

    // admin-selected UI language; falls back to the default when the stored value is unknown.
    public function locale(): string
    {
        $default = (string) config('yurba.locale', 'en');
        $available = array_keys((array) config('yurba.locales', ['en' => 'English']));
        $locale = (string) Store::get('panel_locale', $default);

        return in_array($locale, $available, true) ? $locale : $default;
    }

    /** @return array<string, string> available UI locales: code => label */
    public function locales(): array
    {
        return (array) config('yurba.locales', ['en' => 'English']);
    }

    /** @return string[] */
    public function styles(): array
    {
        return (array) config('yurba.styles', []);
    }

    /** @return string[] */
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

    /** @return Collection<int, Resource> */
    public function resources(): Collection
    {
        $resources = collect(config('yurba.resources', []))
            ->filter(fn ($class) => class_exists($class))
            ->map(fn ($class) => app($class));

        // built-in redirects manager; opt out via config
        if (config('yurba.redirects.enabled', true)) {
            $resources->push(app(RedirectResource::class));
        }

        return $resources->values();
    }

    /** @return Collection<int, Resource> */
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
        return $this->resources()->first(fn (Resource $r) => $r->uriKey() === $uriKey);
    }

    /** @return Collection<int, Settings\SettingsPage> */
    public function settingsPages(): Collection
    {
        $pages = collect(config('yurba.settings', []))
            ->filter(fn ($class) => class_exists($class))
            ->map(fn ($class) => app($class));

        // built-in panel settings; opt out via config
        if (config('yurba.builtin_settings', true)) {
            $pages->prepend(app(PanelSettings::class));
        }

        return $pages->values();
    }

    public function findSettings(string $uriKey): ?Settings\SettingsPage
    {
        return $this->settingsPages()->first(fn ($p) => $p->uriKey() === $uriKey);
    }

    /** @return Collection<int, Pages\Page> custom screens the user may see */
    public function pages(): Collection
    {
        $user = $this->user();

        // each entry is a class-string, a [class, ...ctorArgs] tuple (keeps the
        // config serializable for config:cache), or a ready Page instance
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

        // built-in media screens (hidden from nav, linked from settings)
        if (config('yurba.media.log', true)) {
            $pages->push(app(Pages\MediaOptimizationLogPage::class));
        }
        $pages->push(app(Pages\MediaUsagePage::class));

        return $pages
            ->filter(fn (Pages\Page $p) => $p->canView($user))
            ->values();
    }

    public function findPage(string $uriKey): ?Pages\Page
    {
        return $this->pages()->first(fn ($p) => $p->uriKey() === $uriKey);
    }

    public function authorizeUsing(Closure $callback): void
    {
        $this->gate = $callback;
    }

    // defaults to a truthy is_admin unless a custom gate is set
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

    public function url(string $path = ''): string
    {
        return url($this->prefix().'/'.ltrim($path, '/'));
    }
}
