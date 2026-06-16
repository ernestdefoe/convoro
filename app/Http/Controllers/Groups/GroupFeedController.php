<?php

namespace App\Http\Controllers\Groups;

use App\Http\Controllers\Controller;
use App\Models\Scopes\SocialGroupTopicScope;
use App\Models\SocialGroup;
use App\Models\Topic;
use App\Support\Present;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class GroupFeedController extends Controller
{
    /** RSS of a public group's recent discussions. Private groups are not fed. */
    public function rss(SocialGroup $group): Response
    {
        abort_if($group->is_private, 404);

        $base = rtrim(config('app.url'), '/');
        $link = $base.'/groups/'.$group->slug;

        $items = Topic::withoutGlobalScope(SocialGroupTopicScope::class)
            ->where('group_id', $group->id)->visible()
            ->with('firstPost')
            ->orderByDesc('last_post_at')->limit(30)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0"><channel>'."\n";
        $xml .= '<title>'.$this->esc($group->name).'</title>'."\n";
        $xml .= '<link>'.$this->esc($link).'</link>'."\n";
        $xml .= '<description>'.$this->esc(Str::limit(strip_tags((string) $group->description), 300)).'</description>'."\n";

        foreach ($items as $t) {
            $url = $link.'/d/'.$t->id;
            $xml .= '<item>'."\n";
            $xml .= '<title>'.$this->esc($t->title).'</title>'."\n";
            $xml .= '<link>'.$this->esc($url).'</link>'."\n";
            $xml .= '<guid isPermaLink="true">'.$this->esc($url).'</guid>'."\n";
            $xml .= '<pubDate>'.optional($t->created_at)->toRssString().'</pubDate>'."\n";
            $xml .= '<description>'.$this->esc(Present::excerpt(optional($t->firstPost)->body_html ?? '', 300)).'</description>'."\n";
            $xml .= '</item>'."\n";
        }

        $xml .= '</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
