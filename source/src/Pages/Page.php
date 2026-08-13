<?php

namespace Yurba\Cmf\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

// a custom panel screen: appears in the sidebar and renders inside the YurbaCMF
// shell. register subclasses in config('yurba.pages'). render() handles GET and
// handle() handles POST — each may return a view/string (wrapped in the panel
// chrome) or a full Response/redirect (used as-is).
abstract class Page
{
    // GET: the screen's content
    abstract public function render(Request $request): mixed;

    // POST: process a submit; redirect back by default
    public function handle(Request $request): mixed
    {
        return back();
    }

    public function label(): string
    {
        return Str::headline(preg_replace('/Page$/', '', class_basename(static::class)));
    }

    // url segment under {prefix}/pages/
    public function uriKey(): string
    {
        return Str::kebab(preg_replace('/Page$/', '', class_basename(static::class)));
    }

    // optional sidebar icon (raw html); null renders a dot
    public function icon(): ?string
    {
        return null;
    }

    // who may open the screen; defaults to any authorized admin
    public function canView($user = null): bool
    {
        return true;
    }

    // show in the sidebar? false = still routable, just hidden (e.g. a screen
    // reached from a hub/list rather than a top-level nav entry)
    public function inNav(): bool
    {
        return true;
    }

    // sidebar group heading; pages sharing a group are listed under it (like the
    // Resources / Settings groups). null = no heading, listed at the top.
    public function group(): ?string
    {
        return null;
    }
}
