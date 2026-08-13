<?php

namespace Yurba\Cmf\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string brand()
 * @method static string prefix()
 * @method static string guard()
 * @method static \Illuminate\Support\Collection resources()
 * @method static \Yurba\Cmf\Resources\Resource|null find(string $uriKey)
 * @method static void authorizeUsing(\Closure $callback)
 * @method static bool authorize(mixed $user)
 * @method static string url(string $path = '')
 *
 * @see \Yurba\Cmf\Panel
 */
class Yurba extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'yurba.cmf';
    }
}
