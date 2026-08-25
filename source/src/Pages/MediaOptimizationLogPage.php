<?php

namespace Yurba\Cmf\Pages;

use Illuminate\Http\Request;
use Yurba\Cmf\Media\MediaOptimization;

// read-only screen listing recent image-optimization outcomes. reached from the
// "Optimize images" setting (and a direct URL); hidden from the sidebar.
class MediaOptimizationLogPage extends Page
{
    public function label(): string
    {
        return __('Optimization log');
    }

    public function uriKey(): string
    {
        return 'media-optimization-log';
    }

    public function icon(): ?string
    {
        return '<span class="material-symbols-rounded">compress</span>';
    }

    // reached from the settings link, not a top-level nav entry
    public function inNav(): bool
    {
        return false;
    }

    public function handle(Request $request): mixed
    {
        if ($request->input('action') === 'clear') {
            MediaOptimization::clear();

            return back()->with('yurba_status', __('Optimization log cleared.'));
        }

        return back();
    }

    public function render(Request $request): mixed
    {
        $filter = (string) $request->query('status', '');

        $query = MediaOptimization::query()->orderByDesc('id');
        if (in_array($filter, [MediaOptimization::STATUS_OPTIMIZED, MediaOptimization::STATUS_SKIPPED, MediaOptimization::STATUS_UNCHANGED], true)) {
            $query->where('status', $filter);
        }

        return view('yurba::media-optimizations', [
            'page' => $this,
            'rows' => $query->paginate(50)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                'all' => MediaOptimization::count(),
                'optimized' => MediaOptimization::where('status', MediaOptimization::STATUS_OPTIMIZED)->count(),
                'skipped' => MediaOptimization::where('status', MediaOptimization::STATUS_SKIPPED)->count(),
            ],
            'enabled' => MediaOptimization::enabled(),
            'keep' => MediaOptimization::keep(),
        ]);
    }
}
