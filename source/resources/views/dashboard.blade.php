@extends('yurba::layout')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="y-cards">
        @forelse($cards as $card)
            <a href="{{ route('yurba.resource.index', $card['uri']) }}" class="y-card">
                <span class="y-card__count">{{ number_format($card['count']) }}</span>
                <span class="y-card__label">{{ $card['label'] }}</span>
            </a>
        @empty
            <div class="y-empty">
                <p>No resources registered yet.</p>
                <p class="y-muted">Add resource classes to <code>config/yurba.php</code>.</p>
            </div>
        @endforelse
    </div>
@endsection
