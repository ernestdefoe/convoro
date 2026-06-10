<?php

namespace App\Http\Controllers;

use App\Support\ExtensionManager;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forum-facing extensions & themes discovery page. Read-only showcase of what's
 * available (currently the installed set; the remote storefront catalog plugs
 * in here later). No admin actions — just browse.
 */
class ExtensionsPageController extends Controller
{
    public function index(): Response
    {
        $enabled = ExtensionManager::enabledIds();

        $items = collect(ExtensionManager::all())
            ->values()
            ->map(fn ($m) => [
                'id' => $m['id'],
                'name' => $m['name'],
                'version' => $m['version'],
                'description' => $m['description'],
                'author' => $m['author'],
                'type' => $m['type'],
                'premium' => $m['premium'],
                'price' => $m['price'],
                'active' => in_array($m['id'], $enabled, true),
            ]);

        return Inertia::render('Extensions/Index', [
            'items' => $items,
        ]);
    }
}
