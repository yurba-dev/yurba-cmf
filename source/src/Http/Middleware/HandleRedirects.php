<?php

namespace Yurba\Cmf\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Yurba\Cmf\Redirects\Redirect;

class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $from = Redirect::normalize($request->path());

        $prefix = trim((string) config('yurba.prefix', 'admin'), '/');
        if ($prefix != '' && ($from == $prefix || Str::startsWith($from, $prefix.'/'))) {
            return $next($request);
        }

        // global middleware: a missing table (package installed, not migrated yet) must not take every page down
        try {
            $map = Redirect::map();
        } catch (\Throwable $e) {
            return $next($request);
        }
        if (! isset($map[$from])) {
            return $next($request);
        }

        $hit = $map[$from];
        $target = $hit['to'];

        // loop guard: a chain back to a visited path (or an absolute url to this host) is ignored rather than served as a 301 loop
        if (Redirect::loops($from, $map, $request->getHost())) {
            return $next($request);
        }

        // same test as Redirect::internalPath(), so "HTTPS://..." isn't served as a local path
        $to = preg_match('#^(https?:)?//#i', $target)
            ? $target
            : url('/'.ltrim($target, '/'));

        if (config('yurba.redirects.log_hits', true)) {
            // query-builder update: no model events, so the map cache is untouched
            DB::table('yurba_redirects')->where('id', $hit['id'])->increment('hits');
        }

        return redirect()->to($to, $hit['status']);
    }
}
