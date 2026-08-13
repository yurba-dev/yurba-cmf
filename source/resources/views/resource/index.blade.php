@extends('yurba::layout')

@section('title', $res->pluralLabel())
@section('heading', $res->pluralLabel())
@section('titleCount', number_format($records->total()))

@section('content')
    @php($fields = $res->indexFields())
    @php($bulk = $res->bulkActions())
    @php($reorder = $res->reorderable() && ! request()->filled('sort'))
    @php($colspan = count($fields) + 1 + (count($bulk) ? 1 : 0) + ($reorder ? 1 : 0))

    @php($filters = $res->filters())
    @php($filterValues = (array) request()->query('f', []))
    @php($trashedMode = request()->query('trashed'))
    @php($ui = (bool) config('yurba.ui.select', true))
    @php($hasFilterUi = count($filters) > 0 || $res->usesSoftDeletes())
    @php($activeCount = collect($filterValues)->flatten()->filter(fn ($v) => filled($v))->count() + (filled($trashedMode) ? 1 : 0))
    @php($hasActive = $search !== '' || $activeCount > 0)

    @php($indexUrl = route('yurba.resource.index', $res->uriKey()))

    <div class="y-listbar" data-ui="{{ $ui ? '1' : '0' }}">
        <div class="y-toolbar">
            {{-- Search: its own form; preserves current filters/sort as hidden inputs. --}}
            <form method="GET" action="{{ $indexUrl }}" class="y-toolbar__search">
                <input type="search" name="q" value="{{ $search }}" class="y-filters__search"
                       placeholder="Search {{ \Illuminate\Support\Str::lower($res->pluralLabel()) }}…">
                @include('yurba::partials.query-hidden', ['data' => request()->except(['q', 'page'])])
            </form>

            @if($hasFilterUi)
                <button type="button" class="y-btn y-btn__ghost" data-filter-toggle aria-expanded="false">
                    <span class="material-symbols-rounded">tune</span> Filters
                    @if($activeCount)<span class="y-filterbadge">{{ $activeCount }}</span>@endif
                </button>
            @endif

            <span class="y-toolbar__actions">
                @if($res->canExport())
                    <a href="{{ route('yurba.resource.export', array_merge(['resource' => $res->uriKey()], request()->query())) }}"
                       class="y-btn y-btn__ghost">Export CSV</a>
                @endif
                @if($res->canImport())
                    <a href="{{ route('yurba.resource.import', $res->uriKey()) }}" class="y-btn y-btn__ghost">Import CSV</a>
                @endif
                @if($res->canCreate($yurbaUser))
                    <a href="{{ route('yurba.resource.create', $res->uriKey()) }}" class="y-btn y-btn__primary">New {{ $res->label() }}</a>
                @endif
            </span>
        </div>

        @if($hasFilterUi)
            {{-- Filter form: self-contained so it still submits after YurbaUI.Dropdown
                 relocates it into a body-mounted menu. Carries q/sort/dir as hidden. --}}
            <form method="GET" action="{{ $indexUrl }}" class="y-filterpanel" data-filter-panel data-active="{{ $activeCount }}" hidden>
                <input type="hidden" name="q" value="{{ $search }}">
                @if($sort)<input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">@endif

                <div class="y-filterpanel__grid">
                    @foreach($filters as $filter)
                        @include($filter->component(), ['filter' => $filter, 'value' => $filterValues[$filter->key] ?? null])
                    @endforeach
                    @if($res->usesSoftDeletes())
                        <label class="y-filter">
                            <span class="y-filter__label">Show</span>
                            <select name="trashed" class="y-input y-filter__control" @if($ui) data-yurba-select @endif>
                                <option value="">Active</option>
                                <option value="only" @selected($trashedMode === 'only')>Trashed</option>
                                <option value="with" @selected($trashedMode === 'with')>All</option>
                            </select>
                        </label>
                    @endif
                </div>
                <div class="y-filterpanel__actions">
                    <button type="submit" class="y-btn y-btn__primary y-btn__xs">Apply</button>
                    @if($hasActive)<a href="{{ $indexUrl }}" class="y-btn y-btn__ghost y-btn__xs">Reset</a>@endif
                </div>

                @if($ui)@include('yurba::partials.ui-select')@endif
            </form>
        @endif
    </div>

    @if(count($bulk))
        {{-- Checkboxes reference this form via their form="" attribute, so it can
             live outside the table without nesting forms in the rows. --}}
        <form method="POST" action="{{ route('yurba.resource.bulk', $res->uriKey()) }}" id="y-bulk-form" class="y-bulkbar" hidden>
            @csrf
            <input type="hidden" name="action" value="">
            <span class="y-bulkbar__count">0 selected</span>
            <span class="y-bulkbar__actions">
                @foreach($bulk as $key => $label)
                    <button type="submit" class="y-btn y-btn__ghost y-btn__xs" data-bulk="{{ $key }}">{{ $label }}</button>
                @endforeach
            </span>
        </form>
    @endif

    <div class="y-table-wrap">
        <table class="y-table">
            <thead>
                <tr>
                    @if($reorder)<th class="y-col-drag"></th>@endif
                    @if(count($bulk))
                        <th class="y-col-check"><input type="checkbox" id="y-check-all" aria-label="Select all"></th>
                    @endif
                    @foreach($fields as $field)
                        <th>
                            @if($field->sortable)
                                @php($isCol = $sort === $field->column())
                                @php($nextDir = ($isCol && $dir === 'asc') ? 'desc' : 'asc')
                                <a href="{{ route('yurba.resource.index', array_merge(request()->except('page'), ['resource' => $res->uriKey(), 'sort' => $field->column(), 'dir' => $nextDir])) }}">
                                    {{ $field->label }}@if($isCol) <span class="y-sort">{{ $dir === 'asc' ? '▲' : '▼' }}</span>@endif
                                </a>
                            @else
                                {{ $field->label }}
                            @endif
                        </th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody @if($reorder) data-reorder-url="{{ route('yurba.resource.reorder', $res->uriKey()) }}" @endif>
                @forelse($records as $record)
                    <tr class="{{ $res->usesSoftDeletes() && $record->trashed() ? 'is-trashed' : '' }}" @if($reorder) draggable="true" data-id="{{ $record->getKey() }}" @endif>
                        @if($reorder)
                            <td class="y-col-drag"><span class="y-drag material-symbols-rounded" aria-hidden="true">drag_indicator</span></td>
                        @endif
                        @if(count($bulk))
                            <td class="y-col-check">
                                <input type="checkbox" class="y-row-check" name="ids[]" value="{{ $record->getKey() }}" form="y-bulk-form" aria-label="Select row">
                            </td>
                        @endif
                        @foreach($fields as $field)
                            <td>@include('yurba::partials.cell', ['field' => $field, 'record' => $record])</td>
                        @endforeach
                        @php($ic = $yurbaActionIcons)
                        <td>
                            <div class="y-col-actions">
                            @if($res->usesSoftDeletes() && $record->trashed())
                                @if($res->canDelete($yurbaUser, $record))
                                    <form method="POST" action="{{ route('yurba.resource.restore', [$res->uriKey(), $record->getKey()]) }}" class="y-inline">
                                        @csrf
                                        <button type="submit" class="y-btn y-btn__ghost y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="Restore" aria-label="Restore" @endif>@include('yurba::partials.action-inner', ['icon' => 'restore', 'text' => 'Restore'])</button>
                                    </form>
                                    <form method="POST" action="{{ route('yurba.resource.forceDelete', [$res->uriKey(), $record->getKey()]) }}" onsubmit="return confirm('Permanently delete this {{ \Illuminate\Support\Str::lower($res->label()) }}? This cannot be undone.');" class="y-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="y-btn y-btn__danger y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="Delete permanently" aria-label="Delete permanently" @endif>@include('yurba::partials.action-inner', ['icon' => 'delete_forever', 'text' => 'Delete permanently'])</button>
                                    </form>
                                @endif
                            @else
                                @if($res->canUpdate($yurbaUser, $record))
                                    @foreach($res->rowActions($record) as $actionKey => $actionDef)
                                        @php($actionLabel = is_array($actionDef) ? ($actionDef['label'] ?? $actionKey) : $actionDef)
                                        @php($actionIcon = is_array($actionDef) ? ($actionDef['icon'] ?? null) : null)
                                        <form method="POST" action="{{ route('yurba.resource.action', $res->uriKey()) }}" class="y-inline">
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $actionKey }}">
                                            <input type="hidden" name="id" value="{{ $record->getKey() }}">
                                            <button type="submit" class="y-btn y-btn__ghost y-btn__xs {{ ($ic && $actionIcon) ? 'y-btn__icon' : '' }}" @if($ic && $actionIcon) title="{{ $actionLabel }}" aria-label="{{ $actionLabel }}" @endif>@if($ic && $actionIcon)<span class="material-symbols-rounded" aria-hidden="true">{{ $actionIcon }}</span>@else{{ $actionLabel }}@endif</button>
                                        </form>
                                    @endforeach
                                @endif
                                @if($res->canView($yurbaUser, $record))
                                    <a href="{{ route('yurba.resource.show', [$res->uriKey(), $record->getKey()]) }}" class="y-btn y-btn__ghost y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="View" aria-label="View" @endif>@include('yurba::partials.action-inner', ['icon' => 'visibility', 'text' => 'View'])</a>
                                @endif
                                @if($res->canUpdate($yurbaUser, $record))
                                    <a href="{{ route('yurba.resource.edit', [$res->uriKey(), $record->getKey()]) }}" class="y-btn y-btn__ghost y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="Edit" aria-label="Edit" @endif>@include('yurba::partials.action-inner', ['icon' => 'edit', 'text' => 'Edit'])</a>
                                @endif
                                @if($res->canDelete($yurbaUser, $record))
                                    <form method="POST" action="{{ route('yurba.resource.destroy', [$res->uriKey(), $record->getKey()]) }}" onsubmit="return confirm('Delete this {{ \Illuminate\Support\Str::lower($res->label()) }}?');" class="y-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="y-btn y-btn__danger y-btn__xs {{ $ic ? 'y-btn__icon' : '' }}" @if($ic) title="Delete" aria-label="Delete" @endif>@include('yurba::partials.action-inner', ['icon' => 'delete', 'text' => 'Delete'])</button>
                                    </form>
                                @endif
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $colspan }}" class="y-empty-row">Nothing found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="y-pagination">{{ $records->links('yurba::pagination') }}</div>

@endsection
