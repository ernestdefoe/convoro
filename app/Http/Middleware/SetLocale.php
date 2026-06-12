<?php

namespace App\Http\Middleware;

use App\Support\I18n;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/** Resolves and applies the request locale (user pref → session → site default). */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = I18n::resolve(
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
        );
        App::setLocale($locale);

        return $next($request);
    }
}
