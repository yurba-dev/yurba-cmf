<?php

namespace Yurba\Cmf\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class Page
{
    abstract public function render(Request $request): mixed;

    public function handle(Request $request): mixed
    {
        return back();
    }

    public function label(): string
    {
        return Str::headline(preg_replace('/Page$/', '', class_basename(static::class)));
    }

    public function uriKey(): string
    {
        return Str::kebab(preg_replace('/Page$/', '', class_basename(static::class)));
    }

    public function icon(): ?string
    {
        return null;
    }

    public function canView($user = null): bool
    {
        return true;
    }

    public function inNav(): bool
    {
        return true;
    }

    public function group(): ?string
    {
        return null;
    }
}
