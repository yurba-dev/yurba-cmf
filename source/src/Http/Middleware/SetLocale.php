<?php

namespace Yurba\Cmf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yurba\Cmf\Facades\Yurba;

// resolve the frontend content locale from the {locale} route slug
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('locale');
        $code = Yurba::localeBySlug($slug) ?? Yurba::defaultLocale();

        Yurba::setContentLocale($code);
        app()->setLocale($code);

        return $next($request);
    }
}
