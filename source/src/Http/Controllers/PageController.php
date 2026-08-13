<?php

namespace Yurba\Cmf\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yurba\Cmf\Pages\Page;

class PageController extends Controller
{
    public function show(Request $request, string $page)
    {
        $screen = $this->resolve($page);

        return $this->respond($screen, $screen->render($request));
    }

    public function handle(Request $request, string $page)
    {
        $screen = $this->resolve($page);

        return $this->respond($screen, $screen->handle($request));
    }

    protected function resolve(string $key): Page
    {
        $panel = app('yurba.cmf');
        $screen = $panel->findPage($key);

        abort_if($screen === null, 404);
        abort_unless($screen->canView($panel->user()), 403);

        return $screen;
    }

    // a full Response is used as-is; a view/string is wrapped in the panel chrome
    protected function respond(Page $screen, mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof View || is_string($result)) {
            return response()->view('yurba::page', [
                'page' => $screen,
                'slot' => $result,
            ]);
        }

        return response($result);
    }
}
