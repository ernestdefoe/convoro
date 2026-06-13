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
            . 'ul,ol,li,code[class],pre[class],a[href|title|rel|target],img[src|alt|width|height],'
            . 'span[class],hr,table,thead,tbody,tr,th,td,'
            . 'details[class],summary,div'
        );
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $cacheDir = storage_path('app/htmlpurifier');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($cacheDir);
        $config->set('Cache.SerializerPath', $cacheDir);

        // <details>/<summary> aren't in HTMLPurifier's default schema, so they
        // must be registered explicitly for spoiler blocks to survive.
        $config->set('HTML.DefinitionID', 'convoro-content');
        $config->set('HTML.DefinitionRev', 1);
        if ($def = $config->maybeGetRawHTMLDefinition()) {
            $def->addElement('details', 'Block', 'Flow', 'Common', ['open' => 'Bool']);
            $def->addElement('summary', 'Block', 'Flow', 'Common');
        }

        return (new HTMLPurifier($config))->purify($html);
    }
}
