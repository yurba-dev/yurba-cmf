@extends('yurba::layout')

@section('title', $res->label().': '.$res->title($record))
@section('heading', $res->label().': '.$res->title($record))

@section('content')
    <div class="y-toolbar">
        <span class="y-toolbar__actions">
            <a href="{{ route('yurba.resource.index', $res->uriKey()) }}" class="y-btn y-btn__ghost">← Back</a>
            @if($preview = $res->previewUrl($record))
                <a href="{{ $preview }}" target="_blank" rel="noopener" class="y-btn y-btn__ghost">
                    <span class="material-symbols-rounded">visibility</span> Preview
                </a>
            @endif
            @if($res->canUpdate($yurbaUser, $record))
                <a href="{{ route('yurba.resource.edit', [$res->uriKey(), $record->getKey()]) }}" class="y-btn y-btn__primary">Edit</a>
            @endif
            @if($res->canDelete($yurbaUser, $record))
                <form method="POST" action="{{ route('yurba.resource.destroy', [$res->uriKey(), $record->getKey()]) }}"
                      onsubmit="return confirm('Delete this {{ \Illuminate\Support\Str::lower($res->label()) }}?');" class="y-inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="y-btn y-btn__danger">Delete</button>
                </form>
            @endif
        </span>
    </div>

    @php
        $tabs = [];
        foreach ($res->fields() as $f) {
            $tabs[$f->tab ?? ''][] = $f;
        }
    @endphp

    <div class="y-form--card">
        @foreach($tabs as $tabName => $tabFields)
            @if($tabName !== '')<h2 class="y-detail__tab">{{ $tabName }}</h2>@endif

            @php
                $bySection = [];
                foreach ($tabFields as $f) { $bySection[$f->section ?? ''][] = $f; }
            @endphp

            @foreach($bySection as $sectionName => $sectionFields)
                @if($sectionName !== '')<h3 class="y-section__title">{{ $sectionName }}</h3>@endif
                <div class="y-detail">
                    @foreach($sectionFields as $field)
                        <div class="y-detail__row">
                            <div class="y-detail__label">{{ $field->label }}</div>
                            <div class="y-detail__value">
                                @include('yurba::partials.detail-value', ['field' => $field, 'record' => $record])
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endforeach
    </div>

    @if($res->hasRevisions())
        @php($revisions = $res->revisions($record))
        <h2 class="y-detail__tab">Revisions</h2>
        @if($revisions->isEmpty())
            <p class="y-muted">No revisions recorded yet.</p>
        @else
            @php($current = $record->getAttributes())
            <div class="y-table-wrap">
                <table class="y-table">
                    <thead>
                        <tr><th>When</th><th>By</th><th>Changed vs current</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($revisions as $rev)
                            @php($changed = $rev->changedFrom($current))
                            <tr>
                                <td>{{ $rev->created_at?->diffForHumans() }} <span class="y-muted">· {{ $rev->created_at }}</span></td>
                                <td>{{ $rev->user_name ?? '—' }}</td>
                                <td>
                                    @if($loop->first && empty($changed))<span class="y-pill y-pill--ok">current</span>
                                    @elseif(empty($changed))<span class="y-muted">no differences</span>
                                    @else{{ implode(', ', array_slice($changed, 0, 6)) }}@if(count($changed) > 6) +{{ count($changed) - 6 }}@endif
                                    @endif
                                </td>
                                <td>
                                    <div class="y-col-actions">
                                        @if(! ($loop->first && empty($changed)) && $res->canUpdate($yurbaUser, $record))
                                            <form method="POST" action="{{ route('yurba.resource.revision.restore', [$res->uriKey(), $record->getKey(), $rev->id]) }}"
                                                  onsubmit="return confirm('Restore this revision? The current state is saved as a new revision.');" class="y-inline">
                                                @csrf
                                                <button type="submit" class="y-btn y-btn__ghost y-btn__xs">Restore</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection
