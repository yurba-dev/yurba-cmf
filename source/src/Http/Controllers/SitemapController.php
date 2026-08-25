<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// public sitemap(s), config-driven (yurba.sitemap): a single urlset, or — when
// yurba.sitemap.sitemaps is set — a <sitemapindex> over named /sitemap-{name}.xml.
// records flagged noindex in yurba_seo are excluded.
class SitemapController extends Controller
{
    // GET /sitemap.xml — a urlset (single mode) or a sitemapindex (multiple mode)
    public function index(): Response
    {
        abort_unless((bool) config('yurba.sitemap.enabled', true), 404);

        $named = $this->named();

        if ($named) {
            return $this->xml($this->renderIndex(array_keys($named)));
        }

        $urls = $this->buildUrls(
            (array) config('yurba.sitemap.static', []),
            (array) config('yurba.sitemap.sources', [])
        );

        return $this->xml($this->renderUrlset($urls));
    }

    // GET /sitemap-{name}.xml — a single named sitemap's urlset
    public function show(string $name): Response
    {
        abort_unless((bool) config('yurba.sitemap.enabled', true), 404);

        $named = $this->named();
        abort_unless(isset($named[$name]), 404);

        $urls = $this->buildUrls(
            (array) ($named[$name]['static'] ?? []),
            (array) ($named[$name]['sources'] ?? [])
        );

        return $this->xml($this->renderUrlset($urls));
    }

    // normalized ['name' => ['static'=>[], 'sources'=>[]], …]; empty = single mode
    protected function named(): array
    {
        $out = [];
        foreach ((array) config('yurba.sitemap.sitemaps', []) as $name => $def) {
            $name = (string) $name;
            if ($name === '' || ! is_array($def)) {
                continue;
            }
            $out[$name] = [
                'static' => (array) ($def['static'] ?? []),
                'sources' => (array) ($def['sources'] ?? []),
            ];
        }

        return $out;
    }

    protected function buildUrls(array $static, array $sources): array
    {
        $urls = [];

        foreach ($static as $entry) {
            $entry = is_array($entry) ? $entry : ['loc' => $entry];
            $urls[] = [
                'loc' => $this->absolute($entry['loc']),
                'lastmod' => isset($entry['lastmod']) ? $this->date($entry['lastmod']) : null,
                'changefreq' => $entry['changefreq'] ?? null,
                'priority' => $entry['priority'] ?? null,
            ];
        }

        foreach ($sources as $source) {
            $urls = array_merge($urls, $this->fromSource((array) $source));
        }

        return $urls;
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

    protected function renderUrlset(array $urls): string
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

    protected function renderIndex(array $names): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($names as $name) {
            $loc = route('yurba.sitemap.named', ['name' => $name]);
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>';
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode("\n", $lines)."\n";
    }

    protected function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
