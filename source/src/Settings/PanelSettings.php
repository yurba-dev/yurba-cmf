<?php

namespace Yurba\Cmf\Settings;

use Yurba\Cmf\Fields\Boolean;
use Yurba\Cmf\Fields\Number;
use Yurba\Cmf\Fields\Text;

// built-in settings for the panel (branding, accent, pagination); auto-registered.
// values are read back by Panel/Resource, overriding the static config when set.
class PanelSettings extends SettingsPage
{
    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">settings</span>';
    }

    public function fields(): array
    {
        return [
            Text::make('panel_brand', 'Brand name')
                ->default(config('yurba.brand'))
                ->help('Shown in the sidebar and page titles. Leave blank to show only the logo.'),

            Text::make('panel_logo', 'Logo URL')
                ->default(config('yurba.logo'))
                ->placeholder('/images/logo.svg')
                ->help('Path or URL to the sidebar logo. Falls back to the bundled Yurba logo.'),

            Boolean::make('panel_hide_brand', 'Hide brand name in sidebar')
                ->default(config('yurba.hide_brand', false))
                ->help('Show only the logo in the sidebar. The brand name above is still used in page titles.'),

            Text::make('panel_accent', 'Accent color')
                ->placeholder('#0d6efd')
                ->rules('nullable|regex:/^#[0-9a-fA-F]{6}$/')
                ->help('Six-digit hex, e.g. #0d6efd. Colours buttons, links and highlights. Blank keeps the default.'),

            Number::make('panel_per_page', 'Rows per page')
                ->default(config('yurba.per_page', 20))
                ->rules('nullable|integer|min:1|max:200')
                ->help('How many records the resource tables list per page.'),

            Boolean::make('panel_action_icons', 'Icon row actions')
                ->default(config('yurba.action_icons', false))
                ->help('Show the table row actions (View / Edit / Delete …) as icons instead of text labels. Needs the icon font (yurba.ui.icons).'),

            Boolean::make('panel_media_optimize', 'Optimize images')
                ->default(config('yurba.media.optimize', true))
                ->help('Downscale and re-encode uploaded images (to yurba.media.max_width / quality) and generate thumbnails.'),
        ];
    }
}
