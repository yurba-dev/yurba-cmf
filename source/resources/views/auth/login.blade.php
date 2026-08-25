<!DOCTYPE html>
<html lang="en" data-yurba>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ $yurbaBrand }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/yurba/main.css') }}">
    @foreach($yurbaStyles as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    {!! $yurbaHead !!}
</head>
<body class="y-body y-login-body">
    <div class="y-login">
        <div class="y-login__brand">
            <img src="{{ $yurbaLogo }}" alt="{{ $yurbaBrand }}" class="y-brand__logo">
            @unless($yurbaHideBrand)
                <span>{{ $yurbaBrand }}</span>
            @endunless
        </div>
        <h1>{{ __('Sign in to the panel') }}</h1>

        @include('yurba::partials.flash')

        <form method="POST" action="{{ route('yurba.login.attempt') }}" class="y-form">
            @csrf
            <div class="y-field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="y-input" required autofocus autocomplete="username">
            </div>
            <div class="y-field">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" class="y-input" required autocomplete="current-password">
            </div>
            <label class="y-check">
                <input type="checkbox" name="remember" value="1"> <span>{{ __('Remember me') }}</span>
            </label>
            <button type="submit" class="y-btn y-btn__primary y-btn__block">{{ __('Sign in') }}</button>
        </form>
    </div>
</body>
</html>
