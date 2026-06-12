<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

/** The convoro.co marketing landing (app-served so it shares login). */
class MarketingController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Marketing/Home', [
            // Whole published catalog — the homepage scrolls through all of them.
            'catalog' => Product::where('published', true)
                ->orderByDesc('featured')->orderBy('name')->get()
                ->map(fn (Product $p) => self::card($p)),
            'seo' => Seo::make([
                'title' => __('Convoro — the AI-native community platform'),
                'description' => __('The forum that answers itself. Convoro puts AI at the core of community: members ask a question and get an instant answer drawn from your forum’s own threads, with citations. Batteries-included, beautiful, and runnable on shared hosting.'),
                'type' => 'website',
            ]),
        ]);
    }

    /** Honest feature-by-feature comparison vs other forum software. */
    public function compare(): Response
    {
        return Inertia::render('Marketing/Compare', [
            'seo' => Seo::make([
                'title' => __('Convoro vs Flarum, Discourse, XenForo, phpBB & Invision'),
                'description' => __('An honest, feature-by-feature comparison of Convoro against the forum software people most often switch from — covering the member experience, AI features, customization, hosting and cost.'),
                'type' => 'website',
            ]),
        ]);
    }

    public static function card(Product $p): array
    {
        return [
            'slug' => $p->slug,
            'name' => $p->name,
            'type' => $p->type,
            'tagline' => $p->tagline,
            'image' => $p->image,
            'price' => $p->priceLabel(),
            'free' => $p->isFree(),
            'review' => ['rating' => $p->review_rating, 'score' => $p->review_score],
        ];
    }
}
