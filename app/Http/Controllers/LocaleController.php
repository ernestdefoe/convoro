<?php

namespace App\Http\Controllers;

use App\Support\I18n;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Switch the UI language (persists to the user when signed in, else the session). */
class LocaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(I18n::available()))],
        ]);

        $request->session()->put('locale', $data['locale']);
        $request->user()?->update(['locale' => $data['locale']]);

        return back();
    }

    /** Toggle "auto-translate posts to my language" for the signed-in member. */
    public function autoTranslate(Request $request): RedirectResponse
    {
        $data = $request->validate(['auto' => ['required', 'boolean']]);
        $request->user()?->update(['auto_translate' => $data['auto']]);

        return back();
    }
}
