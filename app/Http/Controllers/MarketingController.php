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
            'featured' => Product::where('published', true)->where('featured', true)->latest()->take(3)->get()
                ->map(fn (Product $p) => self::card($p)),
            'seo' => Seo::make([
                'title' => 'Convoro — modern community forum software',
                'description' => 'Batteries-included community forum software: live theme editor, WYSIWYG posting, reactions, DMs, PWA push, a built-in marketplace, and a one-click installer. Runs on shared hosting.',
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
        ];
    }
}
