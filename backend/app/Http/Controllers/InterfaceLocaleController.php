<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetInterfaceLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class InterfaceLocaleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetInterfaceLocale::SUPPORTED)],
            'returnTo' => ['required', 'string', 'max:4096', 'regex:/^\/(?!\/)[^\\\\\x00-\x20\x7f]*$/D'],
        ]);

        Cookie::queue(SetInterfaceLocale::COOKIE, $validated['locale'], 60 * 24 * 365, '/', null, null, true, false, 'lax');

        return $request->header('X-Inertia')
            ? Inertia::location($validated['returnTo'])
            : redirect()->away($validated['returnTo'], 303);
    }
}
