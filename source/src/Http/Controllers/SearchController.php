<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yurba\Cmf\Facades\Yurba;

// global search across every resource's searchable columns (sidebar search tab)
class SearchController extends Controller
{
    public function page(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $groups = mb_strlen($term) >= 2 ? $this->results($term, 20) : [];

        return view('yurba::search', compact('term', 'groups'));
    }

    /** @return array<int, array{label: string, icon: ?string, count: int, items: array}> */
    protected function results(string $term, int $limit): array
    {
        $groups = [];
        foreach (Yurba::authorizedResources() as $res) {
            if (! $res->globallySearchable()) {
                continue;
            }

            $columns = $res->searchableColumns();
            if (empty($columns)) {
                continue;
            }

            $records = $res->query()
                ->where(function (Builder $q) use ($columns, $term) {
                    foreach ($columns as $col) {
                        $q->orWhere($col, 'like', "%{$term}%");
                    }
                })
                ->limit($limit)
                ->get();

            if ($records->isEmpty()) {
                continue;
            }

            $groups[] = [
                'label' => $res->pluralLabel(),
                'icon' => $res->icon(),
                'count' => $records->count(),
                'items' => $records->map(fn ($r) => [
                    'title' => $res->title($r),
                    'url' => route('yurba.resource.edit', [$res->uriKey(), $r->getKey()]),
                ])->all(),
            ];
        }

        return $groups;
    }
}
