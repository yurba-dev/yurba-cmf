<?php

namespace Yurba\Cmf\Settings;

use Yurba\Cmf\Fields\Boolean;
use Yurba\Cmf\Fields\Number;
use Yurba\Cmf\Fields\Select;
use Yurba\Cmf\Fields\Text;

// built-in settings for the panel (branding, accent, pagination); auto-registered.
// values are read back by Panel/Resource, overriding the static config when set.
class PanelSettings extends SettingsPage
{
    public function label(): string
    {
        return __('Panel');
    }

    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">settings</span>';
    }

    public function fields(): array
    {
        return [
            Select::make('panel_locale', __('Panel language'))
                ->options(config('yurba.locales', ['en' => 'English']))
                ->default(config('yurba.locale', 'en'))
                ->help(__('Interface language for the admin panel.')),

            Text::make('panel_brand', __('Brand name'))
                ->default(config('yurba.brand'))
                ->help(__('Shown in the sidebar and page titles. Leave blank to show only the logo.')),

            Text::make('panel_logo', __('Logo URL'))
                ->default(config('yurba.logo'))
                ->placeholder('/images/logo.svg')
                ->help(__('Path or URL to the sidebar logo. Falls back to the bundled Yurba logo.')),

            Boolean::make('panel_hide_brand', __('Hide brand name in sidebar'))
                ->default(config('yurba.hide_brand', false))
                ->help(__('Show only the logo in the sidebar. The brand name above is still used in page titles.')),

            Text::make('panel_accent', __('Accent color'))
                ->placeholder('#0d6efd')
                ->rules('nullable|regex:/^#[0-9a-fA-F]{6}$/')
                ->help(__('Six-digit hex, e.g. #0d6efd. Colours buttons, links and highlights. Blank keeps the default.')),

            Number::make('panel_per_page', __('Rows per page'))
                ->default(config('yurba.per_page', 20))
                ->rules('nullable|integer|min:1|max:200')
                ->help(__('How many records the resource tables list per page.')),

            Boolean::make('panel_action_icons', __('Icon row actions'))
                ->default(config('yurba.action_icons', false))
                ->help(__('Show the table row actions (View / Edit / Delete …) as icons instead of text labels. Needs the icon font (yurba.ui.icons).')),

            Boolean::make('panel_media_optimize', __('Optimize images'))
                ->default(config('yurba.media.optimize', true))
                ->help(__('Downscale and re-encode uploaded images (to yurba.media.max_width / quality). Thumbnails are generated on demand, not on upload.'))
                ->helpLink(route('yurba.page.show', 'media-optimization-log'), __('View optimization log'))
                ->helpLink(route('yurba.page.show', 'media-usage'), __('Image usage & thumbnails')),
        ];
    }
}
