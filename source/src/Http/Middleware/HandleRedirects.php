<?php

namespace Yurba\Cmf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Yurba\Cmf\Redirects\Redirect;

// applies admin-managed redirects. global middleware so it fires even for paths
// with no route (old urls). GET/HEAD only, never touches the admin panel.
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $from = Redirect::normalize($request->path());

        // never redirect within the admin panel itself
        $prefix = trim((string) config('yurba.prefix', 'admin'), '/');
        if ($prefix !== '' && ($from === $prefix || Str::startsWith($from, $prefix.'/'))) {
            return $next($request);
        }

        $map = Redirect::map();
        if (! isset($map[$from])) {
            return $next($request);
        }

        $hit = $map[$from];
        $target = $hit['to'];

        // loop guard: a redirect pointing back to its own source is ignored
        if (Redirect::normalize($target) === $from
            && ! Str::startsWith($target, ['http://', 'https://', '//'])) {
            return $next($request);
        }

        $to = Str::startsWith($target, ['http://', 'https://', '//'])
            ? $target
            : url('/'.ltrim($target, '/'));

        if (config('yurba.redirects.log_hits', true)) {
            // query-builder update: no model events, so the map cache is untouched
            DB::table('yurba_redirects')->where('id', $hit['id'])->increment('hits');
        }

        return redirect()->to($to, $hit['status']);
    }
}
