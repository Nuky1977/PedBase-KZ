<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetInterfaceLocale
{
    public const SUPPORTED = ['kk', 'ru', 'en'];

    public const DEFAULT = 'kk';

    public const COOKIE = 'pedbase_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);
        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT);

        return $next($request);
    }
}
