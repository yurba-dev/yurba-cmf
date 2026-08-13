@extends('yurba::layout')

@section('title', 'Search')
@section('heading', 'Search')

@section('content')
    <form method="GET" action="{{ route('yurba.search') }}" class="y-search-form">
        <input type="search" name="q" value="{{ $term }}" autofocus
               placeholder="Search across all resources…" class="y-search-form__input">
        <button type="submit" class="y-btn y-btn__primary">Search</button>
    </form>

    @if($term === '')
        <p class="y-muted">Enter a term to search across every resource.</p>
    @elseif(mb_strlen($term) < 2)
        <p class="y-muted">Type at least 2 characters.</p>
    @elseif(empty($groups))
        <p class="y-muted">No results for “{{ $term }}”.</p>
    @else
        @foreach($groups as $g)
            <section class="y-search-group">
                <h3 class="y-search-group__title">
                    @if($g['icon'])<span class="y-nav__icon">{!! $g['icon'] !!}</span>@endif
                    {{ $g['label'] }}
                    <span class="y-topbar__count">{{ $g['count'] }}</span>
                </h3>
                <div class="y-table-wrap">
                    <table class="y-table">
                        <tbody>
                            @foreach($g['items'] as $item)
                                <tr>
                                    <td>{{ $item['title'] }}</td>
                                    <td class="y-col-actions">
                                        <a href="{{ $item['url'] }}" class="y-btn y-btn__ghost y-btn__xs">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
@endsection
