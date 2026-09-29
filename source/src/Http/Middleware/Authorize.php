<?php

namespace Yurba\Cmf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Yurba\Cmf\Facades\Yurba;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard(Yurba::guard())->user();

        if (! $user) {
            // fetch callers need a status, not the login page; guest() keeps the url so login returns there
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => __('Your session has expired. Sign in again.')], 401);
            }

            return redirect()->guest(route('yurba.login'));
        }

        if (! Yurba::authorize($user)) {
            abort(403, __('You do not have access to this panel.'));
        }

        app()->setLocale(Yurba::locale());

        return $next($request);
    }
}
