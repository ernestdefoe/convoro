<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

/** Branded documentation hub + guides (data-driven from config/docs.php). */
class DocsController extends Controller
{
    private function index_items(): array
    {
        return collect(config('docs', []))
            ->map(fn ($g, $slug) => ['slug' => $slug, 'title' => $g['title'], 'icon' => $g['icon'] ?? '📄', 'intro' => $g['intro'] ?? ''])
            ->values()->all();
    }

    public function index(): Response
    {
        return Inertia::render('Marketing/Docs/Index', [
            'guides' => $this->index_items(),
            'seo' => Seo::make(['title' => __('Documentation — Convoro'), 'description' => __('Guides for installing, configuring, theming, extending, and deploying Convoro.')]),
        ]);
    }

    public function show(string $guide): Response
    {
        $data = config("docs.{$guide}");
        abort_if(! $data, 404);

        return Inertia::render('Marketing/Docs/Show', [
            'guide' => ['slug' => $guide] + $data,
            'nav' => $this->index_items(),
            'seo' => Seo::make(['title' => __(':title — Convoro docs', ['title' => $data['title']]), 'description' => $data['intro'] ?? '']),
        ]);
    }
}
