<?php

namespace Yurba\Cmf\Http\Controllers;

use Yurba\Cmf\Facades\Yurba;
use Yurba\Cmf\Resources\Resource;

class DashboardController extends Controller
{
    public function index()
    {
        $cards = Yurba::authorizedResources()->map(fn (Resource $r) => [
            'label' => $r->pluralLabel(),
            'uri' => $r->uriKey(),
            'count' => $r->query()->count(),
            'icon' => $r->icon(),
        ]);

        return view('yurba::dashboard', compact('cards'));
    }
}
