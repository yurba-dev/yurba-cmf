<!DOCTYPE html>
<html lang="en" data-yurba>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · {{ $yurbaBrand }}</title>
    {{-- YurbaUI base first, so the panel theme (main.css) overrides it. --}}
    @if(config('yurba.ui.select', true))
        <link rel="stylesheet" href="{{ asset('vendor/yurba/yurba-ui.min.css') }}?v={{ \Yurba\Cmf\Fields\Select::UI_ASSET_VERSION }}">
    @endif
    @if(config('yurba.ui.viewer', true))
        <link rel="stylesheet" href="{{ asset('vendor/yurba/yurba-pv.min.css') }}?v={{ \Yurba\Cmf\Fields\Image::VIEWER_ASSET_VERSION }}">
    @endif
    @if(config('yurba.editor.driver') == 'yurba')
        <link rel="stylesheet" href="{{ asset('vendor/yurba/yurba-editor.min.css') }}?v={{ \Yurba\Cmf\Fields\Editor::ASSET_VERSION }}">
    @endif
    @if(config('yurba.ui.icons', true))
        {{-- Font only; the .material-symbols-rounded helper class lives in main.css. --}}
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded">
    @endif
    <link rel="stylesheet" href="{{ asset('vendor/yurba/main.css') }}?v={{ @filemtime(public_path('vendor/yurba/main.css')) }}">
    @foreach($yurbaStyles as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    {!! $yurbaHead !!}
    @if($yurbaAccent)
        <style>[data-yurba]{--y-accent:{{ $yurbaAccent }};--y-accent-ink:{{ $yurbaAccent }};--y-accent-soft:color-mix(in srgb, {{ $yurbaAccent }} 12%, #fff);}</style>
    @endif
</head>
<body class="y-body">
    @php($current = request()->route('resource'))
    <div class="y-backdrop" onclick="document.body.classList.remove('y-nav-open')"></div>
    <div class="y-shell">
        <aside class="y-sidebar">
            <button type="button" class="y-nav-close" aria-label="Close menu" onclick="document.body.classList.remove('y-nav-open')">×</button>
            <a href="{{ route('yurba.dashboard') }}" class="y-brand">
                <img src="{{ $yurbaLogo }}" alt="{{ $yurbaBrand }}" class="y-brand__logo">
                @unless($yurbaHideBrand)
                    <span class="y-brand__name">{{ $yurbaBrand }}</span>
                @endunless
            </a>

            <nav class="y-nav">
                <a href="{{ route('yurba.dashboard') }}" class="y-nav__link {{ request()->routeIs('yurba.dashboard') ? 'is-active' : '' }}">
                    <span class="y-nav__icon"><span class="material-symbols-rounded">space_dashboard</span></span> Dashboard
                </a>
                <a href="{{ route('yurba.search') }}" class="y-nav__link {{ request()->routeIs('yurba.search') ? 'is-active' : '' }}">
                    <span class="y-nav__icon"><span class="material-symbols-rounded">search</span></span> Search
                </a>
                @if(config('yurba.media.enabled', true))
                    <a href="{{ route('yurba.media') }}" class="y-nav__link {{ request()->routeIs('yurba.media') ? 'is-active' : '' }}">
                        <span class="y-nav__icon"><span class="material-symbols-rounded">perm_media</span></span> Media
                    </a>
                @endif

                @foreach($yurbaPages->filter->inNav()->filter(fn ($p) => $p->group() !== 'Settings')->groupBy(fn ($p) => $p->group()) as $group => $groupPages)
                    @if($group)
                        <div class="y-nav__label">{{ $group }}</div>
                    @endif
                    @foreach($groupPages as $p)
                        <a href="{{ route('yurba.page.show', $p->uriKey()) }}"
                           class="y-nav__link {{ request()->routeIs('yurba.page.*') && request()->route('page') === $p->uriKey() ? 'is-active' : '' }}">
                            @if($p->icon())
                                <span class="y-nav__icon">{!! $p->icon() !!}</span>
                            @else
                                <span class="y-nav__dot"></span>
                            @endif
                            {{ $p->label() }}
                        </a>
                    @endforeach
                @endforeach

                <div class="y-nav__label">Resources</div>
                @foreach($yurbaResources as $r)
                    <a href="{{ route('yurba.resource.index', $r->uriKey()) }}"
                       class="y-nav__link {{ $current === $r->uriKey() ? 'is-active' : '' }}">
                        @if($r->icon())
                            <span class="y-nav__icon">{!! $r->icon() !!}</span>
                        @else
                            <span class="y-nav__dot"></span>
                        @endif
                        {{ $r->pluralLabel() }}
                    </a>
                @endforeach

                @php($navSettingsPages = $yurbaPages->filter->inNav()->filter(fn ($p) => $p->group() === 'Settings'))
                @if(count($yurbaSettings) || $navSettingsPages->isNotEmpty())
                    <div class="y-nav__label">Settings</div>
                    @php($currentSettings = request()->route('page'))
                    @foreach($yurbaSettings as $s)
                        <a href="{{ route('yurba.settings.show', $s->uriKey()) }}"
                           class="y-nav__link {{ request()->routeIs('yurba.settings.*') && $currentSettings === $s->uriKey() ? 'is-active' : '' }}">
                            @if($s->icon())
                                <span class="y-nav__icon">{!! $s->icon() !!}</span>
                            @else
                                <span class="y-nav__dot"></span>
                            @endif
                            {{ $s->label() }}
                        </a>
                    @endforeach
                    @foreach($navSettingsPages as $p)
                        <a href="{{ route('yurba.page.show', $p->uriKey()) }}"
                           class="y-nav__link {{ request()->routeIs('yurba.page.*') && request()->route('page') === $p->uriKey() ? 'is-active' : '' }}">
                            @if($p->icon())
                                <span class="y-nav__icon">{!! $p->icon() !!}</span>
                            @else
                                <span class="y-nav__dot"></span>
                            @endif
                            {{ $p->label() }}
                        </a>
                    @endforeach
                @endif
            </nav>

            @if($yurbaUser)
                <div class="y-sidebar__user">
                    <span class="y-user">{{ $yurbaUser->name ?? $yurbaUser->email }}</span>
                    <form method="POST" action="{{ route('yurba.logout') }}">
                        @csrf
                        <button type="submit" class="y-btn y-btn__ghost y-btn__block">Log out</button>
                    </form>
                </div>
            @endif
        </aside>

        <div class="y-main">
            <header class="y-topbar">
                <button type="button" class="y-menu-toggle" aria-label="Menu" onclick="document.body.classList.toggle('y-nav-open')">≡</button>
                <div class="y-topbar__title">@yield('heading', '')@hasSection('titleCount')<span class="y-topbar__count" title="Total records">@yield('titleCount')</span>@endif</div>
                <div class="y-topbar__user">
                    @if($yurbaUser)
                        <span class="y-user">{{ $yurbaUser->name ?? $yurbaUser->email }}</span>
                        <form method="POST" action="{{ route('yurba.logout') }}">
                            @csrf
                            <button type="submit" class="y-btn y-btn__ghost">Log out</button>
                        </form>
                    @endif
                </div>
            </header>

            <main class="y-content">
                @include('yurba::partials.flash')
                @yield('content')
            </main>

            <footer class="y-footer">
                Powered by <a href="https://dev.yurba.one" target="_blank" rel="noopener noreferrer">YurbaCMF</a>
            </footer>
        </div>
    </div>

    @foreach($yurbaScripts as $src)
        <script src="{{ $src }}"></script>
    @endforeach
    @if(config('yurba.editor.driver') == 'yurba')
        <script src="{{ asset('vendor/yurba/yurba-editor.min.js') }}?v={{ \Yurba\Cmf\Fields\Editor::ASSET_VERSION }}" defer></script>
    @endif
    <script src="{{ asset('vendor/yurba/main.js') }}?v={{ @filemtime(public_path('vendor/yurba/main.js')) }}"></script>
    {!! $yurbaFoot !!}
</body>
</html>
