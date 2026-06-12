<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateLocaleJob;
use App\Support\I18n;
use App\Support\Llm;
use App\Support\Settings;
use App\Support\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Languages: manage UI translation. Shows per-locale coverage against
 * the English source catalog, runs AI auto-translation, lets admins add a
 * language or hand-edit any string.
 */
class LanguagesController extends Controller
{
    public function index(): Response
    {
        $coverage = Translator::coverage();

        // Locales we know a label for but that don't exist yet — the "add language" menu.
        $existing = array_keys(I18n::available());
        $addable = [];
        foreach (I18n::known() as $code => $label) {
            if (! in_array($code, $existing, true)) {
                $addable[] = ['code' => $code, 'label' => $label];
            }
        }

        $translating = [];
        foreach ($coverage as $row) {
            $translating[$row['code']] = (bool) Settings::get('i18n.translating.'.$row['code'], false);
        }

        return Inertia::render('Admin/Languages', [
            'coverage' => $coverage,
            'catalogSize' => count(I18n::catalog()),
            'addable' => $addable,
            'aiConfigured' => Llm::configured(),
            'translating' => $translating,
        ]);
    }

    /** Live coverage + translating flags for polling while a job runs. */
    public function status()
    {
        $coverage = Translator::coverage();
        $translating = [];
        foreach ($coverage as $row) {
            $translating[$row['code']] = (bool) Settings::get('i18n.translating.'.$row['code'], false);
        }

        return response()->json(['coverage' => $coverage, 'translating' => $translating]);
    }

    /** Kick off AI translation of all missing strings for a locale (queued). */
    public function translate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => 'required|string',
        ]);
        $locale = $data['locale'];

        abort_unless(array_key_exists($locale, I18n::available()) && $locale !== 'en', 422);
        abort_unless(Llm::configured(), 422, __('No AI provider is configured.'));

        TranslateLocaleJob::dispatch($locale);

        return back()->with('flash', ['type' => 'success', 'message' => __(':language: translation started.', ['language' => I18n::label($locale)])]);
    }

    /** Create a new (empty) locale file so it becomes available, then optionally translate. */
    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => 'required|string|max:16|regex:/^[A-Za-z][A-Za-z0-9-]*$/',
            'translate' => 'sometimes|boolean',
        ]);
        $locale = $data['locale'];

        abort_if(array_key_exists($locale, I18n::available()), 422, __('That language already exists.'));

        Translator::write($locale, []);

        if (($data['translate'] ?? false) && Llm::configured()) {
            TranslateLocaleJob::dispatch($locale);
        }

        return back()->with('flash', ['type' => 'success', 'message' => __(':language added.', ['language' => I18n::label($locale)])]);
    }

    /** Hand-edit a single string in a locale (overrides any AI translation). */
    public function updateString(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => 'required|string',
            'key' => 'required|string',
            'value' => 'present|string',
        ]);

        abort_unless(array_key_exists($data['locale'], I18n::available()) && $data['locale'] !== 'en', 422);

        $dict = Translator::dictionary($data['locale']);
        if (trim($data['value']) === '') {
            unset($dict[$data['key']]);
        } else {
            $dict[$data['key']] = $data['value'];
        }
        Translator::write($data['locale'], $dict);

        return back()->with('flash', ['type' => 'success', 'message' => __('Saved.')]);
    }

    /** Return the full catalog + a locale's current strings for inline editing. */
    public function strings(Request $request)
    {
        $data = $request->validate(['locale' => 'required|string']);
        abort_unless(array_key_exists($data['locale'], I18n::available()), 422);

        $dict = Translator::dictionary($data['locale']);
        $rows = [];
        foreach (I18n::catalog() as $key) {
            $rows[] = ['key' => $key, 'value' => $dict[$key] ?? ''];
        }

        return response()->json(['strings' => $rows]);
    }

    /** Delete a locale entirely. */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => 'required|string']);
        abort_if($data['locale'] === 'en', 422);

        $path = base_path('lang/'.$data['locale'].'.json');
        if (File::exists($path)) {
            File::delete($path);
        }

        // If the site default pointed at the removed locale, fall back to English.
        if (Settings::get('site.locale') === $data['locale']) {
            Settings::set('site.locale', 'en');
        }

        return back()->with('flash', ['type' => 'success', 'message' => __('Language removed.')]);
    }

    /** Set (or clear) the site's default UI language. */
    public function setDefault(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => 'nullable|string']);
        $locale = $data['locale'] ?? '';

        if ($locale === '' || $locale === 'en') {
            Settings::set('site.locale', 'en');
        } else {
            abort_unless(array_key_exists($locale, I18n::available()), 422);
            Settings::set('site.locale', $locale);
        }

        return back()->with('flash', ['type' => 'success', 'message' => __('Default language updated.')]);
    }
}
