<?php

namespace Yurba\Cmf\Pages;

use Illuminate\Http\Request;
use Yurba\Cmf\Facades\Yurba;
use Yurba\Cmf\Settings\Store;

// configure the frontend content languages (code, url slug, label, default).
// hidden from the sidebar; reached from Settings -> Panel -> Enable multilingual.
class LanguagesPage extends Page
{
    public function label(): string
    {
        return __('Languages');
    }

    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">translate</span>';
    }

    public function inNav(): bool
    {
        return false;
    }

    public function render(Request $request): mixed
    {
        return view('yurba::languages', [
            'locales' => Yurba::contentLocales(),
            'default' => Yurba::defaultLocale(),
        ]);
    }

    public function handle(Request $request): mixed
    {
        $locales = [];
        foreach ((array) $request->input('locales', []) as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code === '' || ! preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $code)) {
                continue;
            }
            $locales[$code] = [
                'label' => trim((string) ($row['label'] ?? '')) ?: $code,
                'slug' => trim((string) ($row['slug'] ?? ''), '/') ?: $code,
            ];
        }

        if (! $locales) {
            return back()->with('yurba_status', __('Add at least one language.'));
        }

        Store::set('panel_locales', $locales);

        $default = strtolower(trim((string) $request->input('default', '')));
        if (isset($locales[$default])) {
            Store::set('panel_default_locale', $default);
        }

        return redirect()
            ->route('yurba.page.show', 'languages')
            ->with('yurba_status', __('Languages saved.'));
    }
}
