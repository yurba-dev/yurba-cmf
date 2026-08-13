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
            return redirect()->route('yurba.login');
        }

        if (! Yurba::authorize($user)) {
            abort(403, 'You do not have access to this panel.');
        }

        return $next($request);
    }
}
