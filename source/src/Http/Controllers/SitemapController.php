<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// public /sitemap.xml, config-driven (yurba.sitemap): static urls + model
// sources. records flagged noindex in yurba_seo are excluded.
class SitemapController extends Controller
{
    public function index(): Response
    {
        abort_unless((bool) config('yurba.sitemap.enabled', true), 404);

        $urls = [];

        foreach ((array) config('yurba.sitemap.static', []) as $entry) {
            $entry = is_array($entry) ? $entry : ['loc' => $entry];
            $urls[] = [
                'loc' => $this->absolute($entry['loc']),
                'lastmod' => isset($entry['lastmod']) ? $this->date($entry['lastmod']) : null,
                'changefreq' => $entry['changefreq'] ?? null,
                'priority' => $entry['priority'] ?? null,
            ];
        }

        foreach ((array) config('yurba.sitemap.sources', []) as $source) {
            $urls = array_merge($urls, $this->fromSource($source));
        }

        $xml = $this->render($urls);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    protected function fromSource(array $source): array
    {
        $model = $source['model'] ?? null;
        $route = $source['route'] ?? null;
        if (! $model || ! $route || ! class_exists($model)) {
            return [];
        }

        $key = $source['key'] ?? (new $model)->getRouteKeyName();
        $param = $source['param'] ?? $key;
        $lastmodCol = $source['lastmod'] ?? null;

        $query = $model::query();

        foreach ((array) ($source['scope'] ?? []) as $scope) {
            $query->{$scope}();
        }
        foreach ((array) ($source['filter'] ?? []) as $column => $value) {
            $query->where($column, $value);
        }

        // skip records flagged noindex in the polymorphic seo table
        $instance = new $model;
        $query->whereNotExists(function ($q) use ($instance) {
            $q->select(DB::raw(1))
                ->from('yurba_seo')
                ->whereColumn('yurba_seo.seoable_id', $instance->getQualifiedKeyName())
                ->where('yurba_seo.seoable_type', $instance->getMorphClass())
                ->where('yurba_seo.noindex', true);
        });

        $columns = array_values(array_filter([$instance->getKeyName(), $key, $lastmodCol]));

        $urls = [];
        foreach ($query->select(array_unique($columns))->cursor() as $record) {
            if (($value = $record->{$key}) === null || $value === '') {
                continue;
            }
            $urls[] = [
                'loc' => route($route, [$param => $value]),
                'lastmod' => $lastmodCol ? $this->date($record->{$lastmodCol}) : null,
                'changefreq' => $source['changefreq'] ?? null,
                'priority' => $source['priority'] ?? null,
            ];
        }

        return $urls;
    }

    protected function render(array $urls): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            if (empty($url['loc'])) {
                continue;
            }
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
            if (! empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
            }
            if (! empty($url['changefreq'])) {
                $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
            }
            if (! empty($url['priority'])) {
                $lines[] = '    <priority>'.$url['priority'].'</priority>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    protected function absolute(string $loc): string
    {
        return Str::startsWith($loc, ['http://', 'https://']) ? $loc : url($loc);
    }

    protected function date($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toAtomString();
        } catch (\Throwable) {
            return null;
        }
    }
}
