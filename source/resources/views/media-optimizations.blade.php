@php
    $human = function (?int $bytes): string {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));

        return round($bytes / (1024 ** $i), $i ? 1 : 0).' '.$units[$i];
    };
    $link = fn (string $status) => route('yurba.page.show', 'media-optimization-log').($status ? '?status='.$status : '');
@endphp

<div class="y-optlog">
    @unless($enabled)
        <div class="y-flash y-flash--warn" style="margin-bottom:16px">
            {{ __('Optimization logging is turned off') }} (<code>yurba.media.log</code>). {{ __("New events won't be recorded.") }}
        </div>
    @endunless

    <p class="y-help" style="margin-bottom: 16px;">
        {{ __('Every uploaded image is recorded here (and every image touched by') }} <code>php artisan yurba:media-optimize</code>{{ __('): downscaled or re-encoded, thumbnail generated, and — when skipped — why.') }}
        @if($keep > 0){{ __('Only the most recent :count events are kept — older ones are pruned automatically.', ['count' => number_format($keep)]) }} (<code>yurba.media.log_keep</code>)@else {{ __('The log is unbounded.') }} (<code>yurba.media.log_keep</code> ≤ 0)@endif
    </p>

    <div class="y-toolbar" style="margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div class="y-toolbar__actions">
            <a href="{{ $link('') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === '' ? 'is-active' : '' }}">{{ __('All') }} ({{ $counts['all'] }})</a>
            <a href="{{ $link('optimized') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === 'optimized' ? 'is-active' : '' }}">{{ __('Optimized') }} ({{ $counts['optimized'] }})</a>
            <a href="{{ $link('skipped') }}" class="y-btn y-btn__ghost y-btn__xs {{ $filter === 'skipped' ? 'is-active' : '' }}">{{ __('Skipped') }} ({{ $counts['skipped'] }})</a>
        </div>
        @if($counts['all'] > 0)
            <form method="POST" action="{{ route('yurba.page.handle', 'media-optimization-log') }}"
                  onsubmit="return confirm('{{ __('Clear the entire optimization log?') }}')">
                @csrf
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="y-btn y-btn__danger y-btn__xs">{{ __('Clear log') }}</button>
            </form>
        @endif
    </div>

    <div class="y-table-wrap">
        <table class="y-table">
            <thead>
                <tr>
                    <th>{{ __('Image') }}</th>
                    <th>{{ __('Result') }}</th>
                    <th>{{ __('Size') }}</th>
                    <th>{{ __('Dimensions') }}</th>
                    <th>{{ __('Thumb') }}</th>
                    <th>{{ __('Source') }}</th>
                    <th>{{ __('When') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    @php
                        $ok = $r->status === \Yurba\Cmf\Media\MediaOptimization::STATUS_OPTIMIZED;
                        $skipped = $r->status === \Yurba\Cmf\Media\MediaOptimization::STATUS_SKIPPED;
                        $color = $ok ? '#1a7f37' : ($skipped ? '#9a6700' : 'var(--y-muted, #6b7280)');
                        $icon = $ok ? 'check_circle' : ($skipped ? 'block' : 'remove');
                        $saved = $r->savingsPercent();
                    @endphp
                    <tr>
                        <td title="{{ $r->path }}"><code>{{ \Illuminate\Support\Str::afterLast($r->path, '/') }}</code></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:6px;color:{{ $color }};font-weight:600;text-transform:capitalize">
                                <span class="material-symbols-rounded" style="font-size:18px">{{ $icon }}</span>{{ $r->status }}
                            </span>
                            @if($r->reason)<div class="y-help" style="margin:2px 0 0">{{ $r->reason }}</div>@endif
                        </td>
                        <td>
                            {{ $human($r->orig_size) }}@if($ok && $r->new_size && $r->new_size !== $r->orig_size) → {{ $human($r->new_size) }}@endif
                            @if($saved > 0)<span class="y-help" style="color:#1a7f37"> (−{{ $saved }}%)</span>@endif
                        </td>
                        <td>{{ $r->width && $r->height ? $r->width.'×'.$r->height : '—' }}</td>
                        <td>
                            @if($r->thumbnailed)
                                <span class="material-symbols-rounded" title="{{ __('Thumbnail generated') }}" style="color:#1a7f37;font-size:18px">done</span>
                            @else
                                <span class="material-symbols-rounded" title="{{ __('No thumbnail') }}" style="color:var(--y-muted,#9ca3af);font-size:18px">remove</span>
                            @endif
                        </td>
                        <td><span class="y-help">{{ $r->source }}</span></td>
                        <td><span class="y-help" title="{{ $r->created_at }}">{{ $r->created_at?->diffForHumans() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="y-empty-row">{{ __('No optimization events recorded yet. Upload an image to see it here.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="y-pagination">{{ $rows->links('yurba::pagination') }}</div>
    @endif
</div>
