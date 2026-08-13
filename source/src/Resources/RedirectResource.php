<?php

namespace Yurba\Cmf\Resources;

use Yurba\Cmf\Fields\Boolean;
use Yurba\Cmf\Fields\Number;
use Yurba\Cmf\Fields\Select;
use Yurba\Cmf\Fields\Text;
use Yurba\Cmf\Filters\BooleanFilter;
use Yurba\Cmf\Filters\SelectFilter;
use Yurba\Cmf\Redirects\Redirect;

// built-in crud for url redirects; auto-registered by Panel when yurba.redirects.enabled
class RedirectResource extends Resource
{
    public static string $model = Redirect::class;

    protected const STATUSES = [
        301 => '301 · Permanent',
        302 => '302 · Found (temporary)',
        307 => '307 · Temporary',
        308 => '308 · Permanent',
    ];

    public function pluralLabel(): string
    {
        return 'Redirects';
    }

    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">alt_route</span>';
    }

    public function bulkActions(): array
    {
        return ['delete' => 'Delete selected'];
    }

    public function filters(): array
    {
        return [
            SelectFilter::make('status')->options(self::STATUSES),
            BooleanFilter::make('enabled', 'Enabled'),
        ];
    }

    public function fields(): array
    {
        return [
            Text::make('from', 'From')->searchable()->sortable()
                ->rules('required|string|max:2048')
                ->placeholder('old/page')
                ->help('Incoming path. The leading slash, host and query string are ignored, so “old/page”, “/old/page/” and “old/page?x=1” all match.'),

            Text::make('to', 'To')->searchable()
                ->rules('required|string|max:2048')
                ->placeholder('/new/page or https://example.com')
                ->help('Where to send visitors — an internal path (/new/page) or an absolute URL.'),

            Select::make('status')->options(self::STATUSES)->default(301)
                ->rules('required|in:301,302,307,308')
                ->help('301/308 are permanent (cached by browsers); 302/307 are temporary.'),

            Boolean::make('enabled')->default(true)
                ->help('Turn a redirect off without deleting it.'),

            Number::make('hits')->onlyOnIndex(),
        ];
    }
}
