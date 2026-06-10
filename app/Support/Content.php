<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes rich content (TipTap/ProseMirror HTML) before storage/display.
 * Allow-list only; strips scripts, event handlers and javascript:/data: URLs.
 * Convoro stores sanitized HTML — never Markdown.
 */
class Content
{
    public static function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed',
            'p,br,strong,em,u,s,h1,h2,h3,blockquote,'
            . 'ul,ol,li,code,pre,a[href|title|rel|target],img[src|alt|width|height],'
            . 'span[class],hr,table,thead,tbody,tr,th,td'
        );
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));

        return (new HTMLPurifier($config))->purify($html);
    }
}
