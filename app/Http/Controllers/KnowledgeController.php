<?php

namespace App\Http\Controllers;

use App\Support\AskIndex;
use App\Support\KnowledgeBase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Curated AI knowledge base — admin-authored support articles the assistant
 * grounds answers in (alongside the built-in docs + changelog). Each article is
 * embedded on save so "Ask" can retrieve and cite it.
 */
class KnowledgeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Knowledge', [
            'articles' => KnowledgeBase::articles(),
            'embedReady' => AskIndex::configured(),
            'strict' => (bool) \App\Support\Settings::get('ask.strict', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        KnowledgeBase::saveArticle(null, $data['title'], $data['body'], $data['url'] ?? null);

        return back()->with('status', $this->savedMessage());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $this->validated($request);
        KnowledgeBase::saveArticle($id, $data['title'], $data['body'], $data['url'] ?? null);

        return back()->with('status', $this->savedMessage());
    }

    public function destroy(int $id): RedirectResponse
    {
        KnowledgeBase::deleteArticle($id);

        return back()->with('status', __('Article deleted.'));
    }

    /** @return array{title:string, body:string, url:?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:50000'],
            'url' => ['nullable', 'string', 'max:300'],
        ]);
    }

    private function savedMessage(): string
    {
        return AskIndex::configured()
            ? __('Article saved and indexed.')
            : __('Article saved. Add a Voyage embeddings key so the assistant can use it.');
    }
}
