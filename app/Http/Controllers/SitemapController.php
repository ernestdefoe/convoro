<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [];
        $add = function (string $loc, ?string $lastmod = null, string $freq = 'weekly', string $priority = '0.5') use (&$urls) {
            $urls[] = compact('loc', 'lastmod', 'freq', 'priority');
        };

        $add(url('/'), null, 'hourly', '1.0');
        $add(url('/extensions'), null, 'weekly', '0.6');
        $add(url('/members'), null, 'weekly', '0.4');

        foreach (Category::all() as $c) {
            $add(url('/?category='.$c->slug), null, 'daily', '0.6');
        }

        Topic::query()->latest('last_post_at')->limit(5000)->get(['slug', 'updated_at'])
            ->each(fn (Topic $t) => $add(url('/t/'.$t->slug), optional($t->updated_at)->toAtomString(), 'weekly', '0.7'));

        User::query()->limit(5000)->get(['id', 'updated_at'])
            ->each(fn (User $u) => $add(url('/u/'.$u->id), optional($u->updated_at)->toAtomString(), 'monthly', '0.3'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.htmlspecialchars($u['loc']).'</loc>';
            if ($u['lastmod']) {
                $xml .= '<lastmod>'.$u['lastmod'].'</lastmod>';
            }
            $xml .= '<changefreq>'.$u['freq'].'</changefreq><priority>'.$u['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /install',
            'Disallow: /messages',
            'Disallow: /user/',
            'Disallow: /new',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
