<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yurba\Cmf\Facades\Yurba;

class AuthController extends Controller
{
    public function show()
    {
        if (Auth::guard(Yurba::guard())->check()) {
            return redirect()->route('yurba.dashboard');
        }

        return view('yurba::auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureIsNotRateLimited($request);

        $guard = Yurba::guard();

        if (! Auth::guard($guard)->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), $this->throttleDecay());
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! Yurba::authorize(Auth::guard($guard)->user())) {
            Auth::guard($guard)->logout();
            RateLimiter::hit($this->throttleKey($request), $this->throttleDecay());
            throw ValidationException::withMessages([
                'email' => 'This account is not allowed to access the panel.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->intended(route('yurba.dashboard'));
    }

    // block further attempts once too many failures pile up on this email+IP
    protected function ensureIsNotRateLimited(Request $request): void
    {
        $max = (int) config('yurba.login_throttle.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $max)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Try again in '.ceil($seconds / 60).' minute(s).',
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }

    protected function throttleDecay(): int
    {
        return (int) config('yurba.login_throttle.decay_seconds', 60);
    }

    public function logout(Request $request)
    {
        Auth::guard(Yurba::guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('yurba.login');
    }
}
